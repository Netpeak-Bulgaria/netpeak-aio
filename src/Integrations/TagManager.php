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
 * Google Tag Manager: <head> snippet + <body> noscript iframe + GA4
 * Enhanced Ecommerce dataLayer pushes.
 *
 * Events emitted to window.dataLayer:
 *  - view_item_list   — shop / category / tag archives
 *  - view_item        — single product pages
 *  - add_to_cart      — woocommerce_add_to_cart hook (queued in WC session)
 *  - remove_from_cart — woocommerce_cart_item_removed hook (queued in WC session)
 *  - view_cart        — cart page
 *  - begin_checkout   — checkout page
 *  - purchase         — thank-you page (deduped via order meta)
 *
 * Scope: Classic WooCommerce templates only.
 *
 * @since 0.1.0
 */
final class TagManager extends AbstractIntegration
{
    use WooCommerceEcommerceTrait;

    /**
     * WC session key for queued events fired during POST requests (cart actions).
     */
    private const PENDING_SESSION_KEY = 'ntp_aio_gtm_pending_events';

    /**
     * Order meta flag preventing duplicate purchase events on thank-you reloads.
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
        if (!$this->is_ecommerce_enabled()) {
            return;
        }

        add_action('woocommerce_add_to_cart', [$this, 'capture_add_to_cart'], 10, 4);
        add_action('woocommerce_cart_item_removed', [$this, 'capture_remove_from_cart'], 10, 2);
        add_action('woocommerce_thankyou', [$this, 'render_purchase'], 10, 1);
    }

    /**
     * @return string
     */
    public function render_head(): string
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return '';
        }

        $id = (string) $this->settings->get('gtm.container_id', '');

        ob_start();
        ?>
        <!-- Google Tag Manager (Analytics Netpeak AIO) -->
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?php echo esc_js($id); ?>');
        </script>
        <?php
        $container = (string) ob_get_clean();

        if (!$this->is_ecommerce_enabled()) {
            return $container;
        }

        $pending = $this->flush_pending_events();
        $context = $this->render_context_event();

        return $container . $pending . $context;
    }


    /**
     * @return string
     */
    public function render_body(): string
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return '';
        }

        $id = (string) $this->settings->get('gtm.container_id', '');

        ob_start();
        ?>
        <!-- Google Tag Manager noscript (Analytics Netpeak AIO) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($id); ?>"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <?php
        $noscript = (string) ob_get_clean();

        if (!$this->is_ecommerce_enabled()) {
            return $noscript;
        }

        return $noscript . $this->render_ajax_cart_listener();
    }

    /**
     * Detects current WC page context and renders the corresponding event.
     *
     * @return string
     */
    private function render_context_event(): string
    {
        if (is_product()) {
            return $this->render_view_item();
        }

        if (is_shop() || is_product_category() || is_product_tag()) {
            return $this->render_view_item_list();
        }

        if (is_cart()) {
            return $this->render_view_cart();
        }

        if (is_checkout() && !is_order_received_page()) {
            return $this->render_begin_checkout();
        }

        return '';
    }

    /**
     * @return string
     */
    private function render_view_item(): string
    {
        global $product;

        if (!$product instanceof WC_Product) {
            $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
        }

        if (!$product instanceof WC_Product) {
            return '';
        }

        $payload = [
            'currency' => $this->currency(),
            'value'    => (float) $product->get_price(),
            'items'    => [$this->build_item_payload($product)],
        ];

        return $this->push_event('view_item', $payload);
    }

    /**
     * @return string
     */
    private function render_view_item_list(): string
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

        $payload = [
            'item_list_name' => $list_name,
            'items'          => $items,
        ];

        return $this->push_event('view_item_list', $payload);
    }

    /**
     * @return string
     */
    private function render_view_cart(): string
    {
        $cart = function_exists('WC') ? WC()->cart : null;
        if (!$cart instanceof WC_Cart || $cart->is_empty()) {
            return '';
        }

        $items = $this->build_cart_items_payload($cart);
        if (empty($items)) {
            return '';
        }

        $payload = [
            'currency' => $this->currency(),
            'value'    => (float) $cart->get_total('edit'),
            'items'    => $items,
        ];

        return $this->push_event('view_cart', $payload);
    }

    /**
     * @return string
     */
    private function render_begin_checkout(): string
    {
        $cart = function_exists('WC') ? WC()->cart : null;
        if (!$cart instanceof WC_Cart || $cart->is_empty()) {
            return '';
        }

        $items = $this->build_cart_items_payload($cart);
        if (empty($items)) {
            return '';
        }

        $payload = [
            'currency' => $this->currency(),
            'value'    => (float) $cart->get_total('edit'),
            'items'    => $items,
        ];

        return $this->push_event('begin_checkout', $payload);
    }

    /**
     * Fires on woocommerce_thankyou. Renders inline push directly in body.
     * Deduped via order meta to survive page reloads.
     *
     * @param int $order_id
     *
     * @return void
     */
    public function render_purchase(int $order_id): void
    {
        if ($order_id <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        if ($order->get_meta(self::PURCHASE_FLAG_META) === '1') {
            return;
        }

        $items = $this->build_order_items_payload($order);
        if (empty($items)) {
            return;
        }

        $payload = [
            'transaction_id' => (string) $order->get_order_number(),
            'value'          => (float) $order->get_total(),
            'tax'            => (float) $order->get_total_tax(),
            'shipping'       => (float) $order->get_shipping_total(),
            'currency'       => $order->get_currency(),
            'items'          => $items,
        ];

        echo $this->push_event('purchase', $payload); // phpcs:ignore WordPress.Security.EscapeOutput

        $order->update_meta_data(self::PURCHASE_FLAG_META, '1');
        $order->save();
    }

    /**
     * Queues an add_to_cart event into WC session for the next page render.
     *
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

        $item = $this->build_item_payload($product, $quantity);

        $payload = [
            'currency' => $this->currency(),
            'value'    => (float) $product->get_price() * $quantity,
            'items'    => [$item],
        ];

        $this->enqueue_pending_event('add_to_cart', $payload);
    }

    /**
     * Queues a remove_from_cart event.
     *
     * @param string $cart_item_key
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

        $payload = [
            'currency' => $this->currency(),
            'value'    => (float) $product->get_price() * $quantity,
            'items'    => [$this->build_item_payload($product, $quantity)],
        ];

        $this->enqueue_pending_event('remove_from_cart', $payload);
    }

    /**
     * JS listener for AJAX add_to_cart. Fetches product data and pushes to
     * dataLayer without waiting for a page reload.
     *
     * @return string
     */
   private function render_ajax_cart_listener(): string
    {
        $currency = $this->currency();
        $rest_url = rest_url('netpeak-aio/v1/gtm/product');
        $nonce    = wp_create_nonce('wp_rest');

        ob_start();
        ?>
        <!-- Netpeak AIO: GA4 add_to_cart AJAX listener -->
        <script>
        (function() {
            if (typeof jQuery === 'undefined') return;
            jQuery(document.body).on('added_to_cart', function(event, fragments, cart_hash, button) {
                if (!button || !button.length) return;
                var productId = parseInt(button.attr('data-product_id') || '0', 10);
                var quantity  = parseInt(button.attr('data-quantity') || '1', 10);
                if (!productId) return;

                fetch('<?php echo esc_url($rest_url); ?>?id=' + productId, { headers: { 'X-WP-Nonce': '<?php echo esc_js($nonce); ?>' } })
                    .then(function(r) { return r.json(); })
                    .then(function(item) {
                        if (!item || !item.item_id) return;
                        item.quantity = quantity;
                        window.dataLayer = window.dataLayer || [];
                        window.dataLayer.push({ ecommerce: null });
                        window.dataLayer.push({
                            event: 'add_to_cart',
                            ecommerce: {
                                currency: '<?php echo esc_js($currency); ?>',
                                value: (item.price || 0) * quantity,
                                items: [item]
                            }
                        });
                    })
                    .catch(function() {});
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Flushes queued events from WC session into inline script.
     *
     * @return string
     */
    private function flush_pending_events(): string
    {
        if (!function_exists('WC') || !WC()->session) {
            return '';
        }

        $pending = WC()->session->get(self::PENDING_SESSION_KEY, []);
        if (!is_array($pending) || empty($pending)) {
            return '';
        }

        WC()->session->set(self::PENDING_SESSION_KEY, []);

        $output = '';
        foreach ($pending as $event) {
            $name      = (string) ($event['name'] ?? '');
            $ecommerce = (array) ($event['payload'] ?? []);
            if ($name === '') {
                continue;
            }
            $output .= $this->push_event($name, $ecommerce);
        }

        return $output;
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
     * Renders a window.dataLayer.push() block with ecommerce reset.
     *
     * @param string               $event
     * @param array<string, mixed> $ecommerce
     *
     * @return string
     */
    private function push_event(string $event, array $ecommerce): string
    {
        $payload = wp_json_encode([
            'event'     => $event,
            'ecommerce' => $ecommerce,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return '';
        }

        ob_start();
        ?>
        <!-- Netpeak AIO: GA4 <?php echo esc_html($event); ?> -->
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push(<?php echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>);
        </script>
        <?php
        return (string) ob_get_clean();
    }
}
