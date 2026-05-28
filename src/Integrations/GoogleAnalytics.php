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
 * Google Analytics 4 via gtag.js + Enhanced Ecommerce events.
 *
 * Behavior:
 *  - When `ga4.route_via_gtm` is true, this integration emits NOTHING —
 *    GTM owns the GA4 tag and event delivery.
 *  - Otherwise, gtag.js is enqueued and ecommerce events are added inline.
 *
 * Events emitted:
 *  - view_item_list, view_item, add_to_cart, remove_from_cart,
 *    view_cart, begin_checkout, purchase
 *
 * Scope: Classic WooCommerce templates only.
 *
 * @since 0.1.0
 */
final class GoogleAnalytics extends AbstractIntegration
{
    use WooCommerceEcommerceTrait;

    /**
     * Script handle for the gtag.js loader and all inline events.
     */
    private const SCRIPT_HANDLE = 'ntp-aio-ga4';

    /**
     * WC session key for queued events fired during POST requests.
     */
    private const PENDING_SESSION_KEY = 'ntp_aio_ga4_pending_events';

    /**
     * Order meta flag to prevent duplicate purchase events on reloads.
     */
    private const PURCHASE_FLAG_META = '_ntp_aio_ga4_purchase_tracked';

    /**
     * @return string
     */
    public function key(): string
    {
        return 'ga4';
    }

    /**
     * @return bool
     */
    public function is_configured(): bool
    {
        $id = (string) $this->settings->get('ga4.measurement_id', '');

        return preg_match('/^G-[A-Z0-9]+$/', $id) === 1;
    }

    /**
     * Whether gtag.js should output at all (false when GTM owns the tag).
     *
     * @return bool
     */
    private function is_direct(): bool
    {
        return $this->is_enabled()
            && $this->is_configured()
            && !(bool) $this->settings->get('ga4.route_via_gtm', false);
    }

    /**
     * Whether to emit ecommerce events directly via gtag.
     *
     * @return bool
     */
    public function is_ecommerce_enabled(): bool
    {
        return $this->is_direct() && $this->is_woocommerce_active();
    }

    /**
     * @return void
     */
    public function register(): void
    {
        if (!$this->is_direct()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        if ($this->is_ecommerce_enabled()) {
            add_action('woocommerce_add_to_cart', [$this, 'capture_add_to_cart'], 10, 4);
            add_action('woocommerce_cart_item_removed', [$this, 'capture_remove_from_cart'], 10, 2);
            add_action('woocommerce_thankyou', [$this, 'mark_purchase_pending'], 10, 1);
        }
    }

    /**
     * Enqueues the gtag.js loader and attaches all inline scripts:
     * base config, queued events, context events, and the AJAX listener.
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        if (is_admin()) {
            return;
        }

        $id = (string) $this->settings->get('ga4.measurement_id', '');

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($id),
            [],
            null,
            false
        );
        wp_script_add_data(self::SCRIPT_HANDLE, 'strategy', 'async');

        // Base gtag config.
        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            $this->build_config_js($id),
            'after'
        );

        if (!$this->is_ecommerce_enabled()) {
            return;
        }

        // Queued events from a previous request (add_to_cart, remove_from_cart, purchase).
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
     * @param string $id
     *
     * @return string
     */
    private function build_config_js(string $id): string
    {
        return sprintf(
            'window.dataLayer = window.dataLayer || [];'
            . 'function gtag(){dataLayer.push(arguments);}'
            . "gtag('js', new Date());"
            . "gtag('config', %s);",
            wp_json_encode($id)
        );
    }

    /**
     * Builds the gtag event JS for the current page context.
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

        return $this->gtag_event_js('view_item', [
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

        return $this->gtag_event_js('view_item_list', [
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

        return $this->gtag_event_js('view_cart', [
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

        return $this->gtag_event_js('begin_checkout', [
            'currency' => $this->currency(),
            'value'    => (float) $cart->get_total('edit'),
            'items'    => $items,
        ]);
    }

    /**
     * Builds the purchase event JS on the thank-you page.
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

        $js = $this->gtag_event_js('purchase', [
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
     * woocommerce_thankyou fires in the body, after wp_enqueue_scripts.
     * We only flag the order here; the actual event is emitted by purchase_js()
     * on the next render via is_order_received_page(). For the first thank-you
     * view, purchase_js() already handles it during enqueue.
     *
     * Kept as a safety net for themes with unusual thank-you flows.
     *
     * @param int $order_id
     *
     * @return void
     */
    public function mark_purchase_pending(int $order_id): void
    {
        // No-op: purchase is detected via is_order_received_page() in enqueue_assets().
        // This hook remains registered to keep parity with theme variations.
        unset($order_id);
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
            . "if(!item||!item.item_id||typeof gtag!=='function')return;"
            . 'item.quantity=quantity;'
            . "gtag('event','add_to_cart',{currency:%s,value:(item.price||0)*quantity,items:[item]});"
            . '})'
            . '.catch(function(){});'
            . '});'
            . '})();',
            wp_json_encode($rest_url),
            wp_json_encode($nonce),
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
            $name   = (string) ($event['name'] ?? '');
            $params = (array) ($event['payload'] ?? []);
            if ($name === '') {
                continue;
            }
            $snippets[] = $this->gtag_event_js($name, $params);
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
     * Builds a gtag('event', name, params) JS string.
     * Params are JSON-encoded via wp_json_encode (safe for inline script).
     *
     * @param string               $event
     * @param array<string, mixed> $params
     *
     * @return string
     */
    private function gtag_event_js(string $event, array $params): string
    {
        return sprintf(
            "if(typeof gtag==='function'){gtag('event',%s,%s);}",
            wp_json_encode($event),
            wp_json_encode($params)
        );
    }
}
