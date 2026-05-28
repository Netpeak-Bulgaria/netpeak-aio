<?php

declare(strict_types=1);

namespace Netpeak\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Integrations\Traits\WooCommerceEcommerceTrait;
use WC_Cart;
use WC_Order;
use WC_Product;

/**
 * Google Tag Manager: container loader + GA4 Enhanced Ecommerce dataLayer.
 *
 * Events pushed to window.dataLayer:
 *  - view_item_list, view_item, add_to_cart, remove_from_cart,
 *    view_cart, begin_checkout, purchase
 *
 * Scope: Classic WooCommerce templates only.
 *
 * @since 0.1.0
 */
final class TagManager extends AbstractIntegration
{
    use WooCommerceEcommerceTrait;

    /**
     * Anchor handle for the inline GTM container and dataLayer pushes.
     */
    private const SCRIPT_HANDLE = 'ntp-aio-gtm';

    /**
     * WC session key for events queued during POST requests (cart actions).
     */
    private const PENDING_SESSION_KEY = 'ntp_aio_gtm_pending_events';

    /**
     * Order meta flag preventing duplicate purchase events on reloads.
     */
    private const PURCHASE_FLAG_META = '_ntp_aio_gtm_purchase_tracked';

    /**
     * @return string
     */
    public function key(): string
    {
        return 'gtm';
    }

    /**
     * @return bool
     */
    public function is_configured(): bool
    {
        $id = (string) $this->settings->get('gtm.container_id', '');

        return preg_match('/^GTM-[A-Z0-9]+$/', $id) === 1;
    }

    /**
     * Whether to emit ecommerce dataLayer events.
     * Auto-enables when GTM is configured AND WooCommerce is active.
     *
     * @return bool
     */
    public function is_ecommerce_enabled(): bool
    {
        return $this->is_enabled()
            && $this->is_configured()
            && $this->is_woocommerce_active();
    }

    /**
     * @return void
     */
    public function register(): void
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        if ($this->is_ecommerce_enabled()) {
            add_action('woocommerce_add_to_cart', [$this, 'capture_add_to_cart'], 10, 4);
            add_action('woocommerce_cart_item_removed', [$this, 'capture_remove_from_cart'], 10, 2);
        }
    }

    /**
     * Enqueues the GTM container and attaches all inline scripts:
     * container snippet, queued events, context event, AJAX listener.
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        if (is_admin()) {
            return;
        }

        $id = (string) $this->settings->get('gtm.container_id', '');

        wp_register_script(self::SCRIPT_HANDLE, '', [], null, false);
        wp_enqueue_script(self::SCRIPT_HANDLE);

        // GTM container snippet.
        wp_add_inline_script(self::SCRIPT_HANDLE, $this->build_container_js($id), 'after');

        if (!$this->is_ecommerce_enabled()) {
            return;
        }

        // Queued events from a previous request (add_to_cart, remove_from_cart).
        foreach ($this->flush_pending_events() as $js) {
            wp_add_inline_script(self::SCRIPT_HANDLE, $js, 'after');
        }

        // Page-contextual event for the current request.
        $context_js = $this->build_context_event_js();
        if ($context_js !== '') {
            wp_add_inline_script(self::SCRIPT_HANDLE, $context_js, 'after');
        }

        // AJAX add_to_cart listener.
        wp_add_inline_script(self::SCRIPT_HANDLE, $this->build_ajax_listener_js(), 'after');
    }

    /**
     * <noscript> iframe fallback. Cannot be enqueued.
     *
     * @return string
     */
    public function render_body(): string
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return '';
        }

        $id  = (string) $this->settings->get('gtm.container_id', '');
        $src = 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode($id);

        return sprintf(
            '<noscript><iframe src="%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>',
            esc_url($src)
        );
    }

    /**
     * @param string $id
     *
     * @return string
     */
    private function build_container_js(string $id): string
    {
        return sprintf(
            "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':"
            . "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],"
            . "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src="
            . "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
            . "})(window,document,'script','dataLayer',%s);",
            wp_json_encode($id)
        );
    }

    /**
     * Builds the dataLayer push JS for the current page context.
     *
     * @return string
     */
    private function build_context_event_js(): string
    {
        if (is_product()) {
            return $this->view_item_js();
        }

        if (is_shop() || is_product_category() || is_product_tag()) {
            return $this->view_item_list_js();
        }

        if (is_cart()) {
            return $this->view_cart_js();
        }

        if (is_checkout() && !is_order_received_page()) {
            return $this->begin_checkout_js();
        }

        if (is_order_received_page()) {
            return $this->purchase_js();
        }

        return '';
    }

    /**
     * @return string
     */
    private function view_item_js(): string
    {
        global $product;

        if (!$product instanceof WC_Product) {
            $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
        }

        if (!$product instanceof WC_Product) {
            return '';
        }

        return $this->push_event_js('view_item', [
            'currency' => $this->currency(),
            'value'    => (float) $product->get_price(),
            'items'    => [$this->build_item_payload($product)],
        ]);
    }

    /**
     * @return string
     */
    private function view_item_list_js(): string
    {
        global $wp_query;

        if (!isset($wp_query->posts) || !is_array($wp_query->posts)) {
            return '';
        }

        $list_name = $this->current_list_name();
        $items     = $this->build_list_items_payload($wp_query->posts, $list_name);

        if (empty($items)) {
            return '';
        }

        return $this->push_event_js('view_item_list', [
            'item_list_name' => $list_name,
            'items'          => $items,
        ]);
    }

    /**
     * @return string
     */
    private function view_cart_js(): string
    {
        $cart = function_exists('WC') ? WC()->cart : null;
        if (!$cart instanceof WC_Cart || $cart->is_empty()) {
            return '';
        }

        $items = $this->build_cart_items_payload($cart);
        if (empty($items)) {
            return '';
        }

        return $this->push_event_js('view_cart', [
            'currency' => $this->currency(),
            'value'    => (float) $cart->get_total('edit'),
            'items'    => $items,
        ]);
    }

    /**
     * @return string
     */
    private function begin_checkout_js(): string
    {
        $cart = function_exists('WC') ? WC()->cart : null;
        if (!$cart instanceof WC_Cart || $cart->is_empty()) {
            return '';
        }

        $items = $this->build_cart_items_payload($cart);
        if (empty($items)) {
            return '';
        }

        return $this->push_event_js('begin_checkout', [
            'currency' => $this->currency(),
            'value'    => (float) $cart->get_total('edit'),
            'items'    => $items,
        ]);
    }

    /**
     * Builds the purchase dataLayer push on the thank-you page.
     * Deduplicated via order meta to survive reloads.
     *
     * @return string
     */
    private function purchase_js(): string
    {
        $order_id = absint(get_query_var('order-received'));
        if ($order_id <= 0 || !function_exists('wc_get_order')) {
            return '';
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return '';
        }

        if ($order->get_meta(self::PURCHASE_FLAG_META) === '1') {
            return '';
        }

        $items = $this->build_order_items_payload($order);
        if (empty($items)) {
            return '';
        }

        $js = $this->push_event_js('purchase', [
            'transaction_id' => (string) $order->get_order_number(),
            'value'          => (float) $order->get_total(),
            'tax'            => (float) $order->get_total_tax(),
            'shipping'       => (float) $order->get_shipping_total(),
            'currency'       => $order->get_currency(),
            'items'          => $items,
        ]);

        $order->update_meta_data(self::PURCHASE_FLAG_META, '1');
        $order->save();

        return $js;
    }

    /**
     * @param string $cart_item_key
     * @param int    $product_id
     * @param int    $quantity
     * @param int    $variation_id
     *
     * @return void
     */
    public function capture_add_to_cart(
        string $cart_item_key,
        int $product_id,
        int $quantity,
        int $variation_id
    ): void {
        /** @noinspection PhpUnusedParameterInspection */
        unset($cart_item_key);

        if (!function_exists('WC') || !WC()->session || !function_exists('wc_get_product')) {
            return;
        }

        $effective_id = $variation_id ?: $product_id;
        $product      = wc_get_product($effective_id);
        if (!$product instanceof WC_Product) {
            return;
        }

        $this->enqueue_pending_event('add_to_cart', [
            'currency' => $this->currency(),
            'value'    => (float) $product->get_price() * $quantity,
            'items'    => [$this->build_item_payload($product, $quantity)],
        ]);
    }

    /**
     * @param string       $cart_item_key
     * @param WC_Cart|null $cart
     *
     * @return void
     */
    public function capture_remove_from_cart(string $cart_item_key, $cart = null): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        if (!$cart instanceof WC_Cart || !method_exists($cart, 'get_removed_cart_contents')) {
            return;
        }

        $removed = $cart->get_removed_cart_contents();
        if (!isset($removed[$cart_item_key])) {
            return;
        }

        $removed_item = $removed[$cart_item_key];
        $product_id   = (int) ($removed_item['product_id'] ?? 0);
        $quantity     = (int) ($removed_item['quantity'] ?? 1);

        if ($product_id <= 0 || !function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product) {
            return;
        }

        $this->enqueue_pending_event('remove_from_cart', [
            'currency' => $this->currency(),
            'value'    => (float) $product->get_price() * $quantity,
            'items'    => [$this->build_item_payload($product, $quantity)],
        ]);
    }

    /**
     * Builds the AJAX add_to_cart listener JS.
     *
     * @return string
     */
    private function build_ajax_listener_js(): string
    {
        $rest_url = rest_url('netpeak-aio/v1/gtm/product');
        $nonce    = wp_create_nonce('wp_rest');

        return sprintf(
            '(function(){'
            . "if(typeof jQuery==='undefined')return;"
            . "jQuery(document.body).on('added_to_cart',function(e,fragments,cartHash,button){"
            . 'if(!button||!button.length)return;'
            . "var productId=parseInt(button.attr('data-product_id')||'0',10);"
            . "var quantity=parseInt(button.attr('data-quantity')||'1',10);"
            . 'if(!productId)return;'
            . "fetch(%s+'?id='+productId,{headers:{'X-WP-Nonce':%s}})"
            . '.then(function(r){return r.json();})'
            . '.then(function(item){'
            . 'if(!item||!item.item_id)return;'
            . 'item.quantity=quantity;'
            . 'window.dataLayer=window.dataLayer||[];'
            . 'window.dataLayer.push({ecommerce:null});'
            . 'window.dataLayer.push({event:%s,ecommerce:{currency:%s,value:(item.price||0)*quantity,items:[item]}});'
            . '})'
            . '.catch(function(){});'
            . '});'
            . '})();',
            wp_json_encode($rest_url),
            wp_json_encode($nonce),
            wp_json_encode('add_to_cart'),
            wp_json_encode($this->currency())
        );
    }

    /**
     * Flushes queued events from WC session into an array of JS snippets.
     *
     * @return array<int, string>
     */
    private function flush_pending_events(): array
    {
        if (!function_exists('WC') || !WC()->session) {
            return [];
        }

        $pending = WC()->session->get(self::PENDING_SESSION_KEY, []);
        if (!is_array($pending) || empty($pending)) {
            return [];
        }

        WC()->session->set(self::PENDING_SESSION_KEY, []);

        $snippets = [];
        foreach ($pending as $event) {
            $name      = (string) ($event['name'] ?? '');
            $ecommerce = (array) ($event['payload'] ?? []);
            if ($name === '') {
                continue;
            }
            $snippets[] = $this->push_event_js($name, $ecommerce);
        }

        return $snippets;
    }

    /**
     * @param string               $name
     * @param array<string, mixed> $payload
     *
     * @return void
     */
    private function enqueue_pending_event(string $name, array $payload): void
    {
        $pending = WC()->session->get(self::PENDING_SESSION_KEY, []);
        $pending = is_array($pending) ? $pending : [];

        $pending[] = ['name' => $name, 'payload' => $payload];

        WC()->session->set(self::PENDING_SESSION_KEY, $pending);
    }

    /**
     * Builds a window.dataLayer.push() JS string with ecommerce reset.
     * Payload is JSON-encoded via wp_json_encode (safe for inline script).
     *
     * @param string               $event
     * @param array<string, mixed> $ecommerce
     *
     * @return string
     */
    private function push_event_js(string $event, array $ecommerce): string
    {
        return sprintf(
            'window.dataLayer=window.dataLayer||[];'
            . 'window.dataLayer.push({ecommerce:null});'
            . 'window.dataLayer.push(%s);',
            wp_json_encode([
                'event'     => $event,
                'ecommerce' => $ecommerce,
            ])
        );
    }
}
