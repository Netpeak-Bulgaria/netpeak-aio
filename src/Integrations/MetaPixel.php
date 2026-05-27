<?php

declare(strict_types=1);

namespace Netpeak\Integrations;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta Pixel (client-side) integration.
 *
 * Renders the fbevents.js loader in <head> and emits event-specific tracking
 * calls based on the current page / request context:
 *
 *  - PageView         — every non-admin page
 *  - ViewContent      — single product pages (is_product)
 *  - Search           — search results pages (is_search)
 *  - AddToCart        — on `woocommerce_add_to_cart` hook (stores event for next render)
 *  - InitiateCheckout — checkout page (is_checkout)
 *  - Purchase         — thank-you page (is_wc_endpoint_url('order-received'))
 *
 * Each event is fired with a unique event_id so that when CAPI sends the same
 * event server-side, Meta will deduplicate by (event_name, event_id).
 *
 * @since 0.1.0
 */
final class MetaPixel extends AbstractIntegration
{
    /**
     * Transient key template for deferred events (e.g. AddToCart fired during redirect).
     */
    private const DEFERRED_TRANSIENT_PREFIX = 'ntp_aio_meta_pixel_deferred_';

    /**
     * @return string
     */
    public function key(): string
    {
        return 'meta.pixel';
    }

    /**
     * @return bool
     */
    public function is_configured(): bool
    {
        $id = (string) $this->settings->get('meta.pixel_id', '');

        return preg_match('/^\d{15,16}$/', $id) === 1;
    }

    /**
     * Registers WooCommerce action hooks that capture events unrelated to page rendering.
     *
     * @return void
     */
    public function register(): void
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return;
        }

        // AddToCart fires during a POST that usually redirects — we defer it
        // into a per-session transient and flush it on the next page render.
        add_action('woocommerce_add_to_cart', [$this, 'capture_add_to_cart'], 10, 6);
    }

    /**
     * Base loader + PageView (fires on every page).
     *
     * @return string
     */
    public function render_head(): string
    {
        if (!$this->is_enabled() || !$this->is_configured() || is_admin()) {
            return '';
        }

        $pixel_id      = (string) $this->settings->get('meta.pixel_id', '');
        $events        = $this->build_events_for_current_request();
        $events_js     = $this->render_events_js($events);
        $view_event_id = $this->event_id_for('PageView');

        ob_start();
        ?>
        <!-- Meta Pixel (Analytics Netpeak AIO) -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo esc_js($pixel_id); ?>');
        fbq('track', 'PageView', {}, { eventID: '<?php echo esc_js($view_event_id); ?>' });
        <?php echo $events_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </script>
        <!-- End Meta Pixel -->
        <?php
        return (string) ob_get_clean();
    }

    /**
     * <noscript> fallback for Pixel (covers only PageView; CAPI handles the rest).
     *
     * @return string
     */
    public function render_body(): string
    {
        if (!$this->is_enabled() || !$this->is_configured() || is_admin()) {
            return '';
        }

        $pixel_id = (string) $this->settings->get('meta.pixel_id', '');

        ob_start();
        ?>
        <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1" alt=""
        /></noscript>
        <?php
        return (string) ob_get_clean();
    }
    /**
     * Captures AddToCart into a short-lived transient keyed by session id.
     * The next page render will include the corresponding fbq('track', ...) call.
     *
     * @param string $cart_item_key
     * @param int    $product_id
     * @param int    $quantity
     * @param int    $variation_id
     * @param array  $variation
     * @param array  $cart_item_data
     *
     * @return void
     */
    public function capture_add_to_cart(
        string $cart_item_key,
        int $product_id,
        int $quantity,
        int $variation_id,
        array $variation,
        array $cart_item_data
    ): void {
        $session_key = $this->session_key();
        if ($session_key === '') {
            return;
        }

        $effective_id = $variation_id ?: $product_id;
        $product      = function_exists('wc_get_product') ? wc_get_product($effective_id) : null;
        if (!$product) {
            return;
        }

        $event = [
            'name'     => 'AddToCart',
            'event_id' => $this->event_id_for('AddToCart', (string) $effective_id),
            'params'   => [
                'content_ids'  => [(string) $effective_id],
                'content_type' => 'product',
                'content_name' => $product->get_name(),
                'value'        => (float) $product->get_price() * $quantity,
                'currency'     => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR',
                'contents'     => [[
                    'id'         => (string) $effective_id,
                    'quantity'   => $quantity,
                    'item_price' => (float) $product->get_price(),
                ]],
            ],
        ];
        \set_transient(self::DEFERRED_TRANSIENT_PREFIX . $session_key, [$event], 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Builds the list of fbq('track', ...) events to emit on the current render.
     *
     * @return array<int, array{name:string, event_id:string, params:array<string,mixed>}>
     */
    private function build_events_for_current_request(): array
    {
        $events = [];

        // Deferred events (e.g. AddToCart from a prior request)
        $session_key = $this->session_key();
        if ($session_key !== '') {
            $deferred = get_transient(self::DEFERRED_TRANSIENT_PREFIX . $session_key);
            if (is_array($deferred) && !empty($deferred)) {
                foreach ($deferred as $e) {
                    if (is_array($e) && isset($e['name'], $e['event_id'], $e['params'])) {
                        $events[] = $e;
                    }
                }
                delete_transient(self::DEFERRED_TRANSIENT_PREFIX . $session_key);
            }
        }

        // Page-contextual events
        if (function_exists('is_product') && is_product()) {
            $e = $this->build_view_content_event();
            if ($e !== null) {
                $events[] = $e;
            }
        } elseif (function_exists('is_search') && is_search()) {
            $events[] = $this->build_search_event();
        } elseif (function_exists('is_checkout') && is_checkout() && !$this->is_thank_you_page()) {
            $e = $this->build_initiate_checkout_event();
            if ($e !== null) {
                $events[] = $e;
            }
        }

        if ($this->is_thank_you_page()) {
            $order_id = $this->detect_thank_you_order_id();
            if ($order_id > 0) {
                $e = $this->build_purchase_event($order_id);
                if ($e !== null) {
                    $events[] = $e;
                }
            }
        }

        return $events;
    }

    /**
     * @return array{name:string, event_id:string, params:array<string,mixed>}|null
     */
    private function build_view_content_event(): ?array
    {
        $product_id = (int) get_queried_object_id();
        if ($product_id <= 0 || !function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }

        $categories = function_exists('wp_get_post_terms')
            ? wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names'])
            : [];
        $category = is_array($categories) && !empty($categories) ? (string) $categories[0] : '';

        return [
            'name'     => 'ViewContent',
            'event_id' => $this->event_id_for('ViewContent', (string) $product_id),
            'params'   => [
                'content_ids'      => [(string) $product_id],
                'content_type'     => 'product',
                'content_name'     => $product->get_name(),
                'content_category' => $category,
                'value'            => (float) $product->get_price(),
                'currency'         => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR',
            ],
        ];
    }

    /**
     * @return array{name:string, event_id:string, params:array<string,mixed>}
     */
    private function build_search_event(): array
    {
        $query = function_exists('get_search_query') ? (string) get_search_query() : '';

        return [
            'name'     => 'Search',
            'event_id' => $this->event_id_for('Search', $query),
            'params'   => [
                'search_string' => $query,
            ],
        ];
    }

    /**
     * @return array{name:string, event_id:string, params:array<string,mixed>}|null
     */
    private function build_initiate_checkout_event(): ?array
    {
        if (!function_exists('WC')) {
            return null;
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return null;
        }

        $ids      = [];
        $contents = [];
        foreach ($cart->get_cart() as $cart_item) {
            $pid = (int) ($cart_item['variation_id'] ?: $cart_item['product_id']);
            if ($pid <= 0) {
                continue;
            }
            $product = wc_get_product($pid);
            if (!$product) {
                continue;
            }
            $ids[]      = (string) $pid;
            $contents[] = [
                'id'         => (string) $pid,
                'quantity'   => (int) $cart_item['quantity'],
                'item_price' => (float) $product->get_price(),
            ];
        }

        if (empty($ids)) {
            return null;
        }

        return [
            'name'     => 'InitiateCheckout',
            'event_id' => $this->event_id_for('InitiateCheckout', $this->session_key()),
            'params'   => [
                'content_ids'  => $ids,
                'content_type' => 'product',
                'contents'     => $contents,
                'num_items'    => (int) $cart->get_cart_contents_count(),
                'value'        => (float) $cart->get_total('edit'),
                'currency'     => get_woocommerce_currency(),
            ],
        ];
    }

    /**
     * @param int $order_id
     *
     * @return array{name:string, event_id:string, params:array<string,mixed>}|null
     */
    private function build_purchase_event(int $order_id): ?array
    {
        if (!function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }

        $ids      = [];
        $contents = [];
        foreach ($order->get_items() as $item) {
            $pid = (int) ($item->get_variation_id() ?: $item->get_product_id());
            if ($pid <= 0) {
                continue;
            }
            $ids[]      = (string) $pid;
            $contents[] = [
                'id'         => (string) $pid,
                'quantity'   => (int) $item->get_quantity(),
                'item_price' => (float) $order->get_item_total($item, false, false),
            ];
        }

        // Persist event_id on the order so CAPI can read the same value later.
        $event_id = (string) $order->get_meta('_ntp_aio_meta_purchase_event_id');
        if ($event_id === '') {
            $event_id = $this->event_id_for('Purchase', (string) $order_id);
            $order->update_meta_data('_ntp_aio_meta_purchase_event_id', $event_id);
            $order->save_meta_data();
        }

        return [
            'name'     => 'Purchase',
            'event_id' => $event_id,
            'params'   => [
                'content_ids'  => $ids,
                'content_type' => 'product',
                'contents'     => $contents,
                'num_items'    => count($contents),
                'value'        => (float) $order->get_total(),
                'currency'     => $order->get_currency(),
                'order_id'     => (string) $order_id,
            ],
        ];
    }

    /**
     * @param array<int, array{name:string, event_id:string, params:array<string,mixed>}> $events
     *
     * @return string
     */
    private function render_events_js(array $events): string
    {
        if (empty($events)) {
            return '';
        }

        $lines = [];
        foreach ($events as $e) {
            $name     = esc_js($e['name']);
            $event_id = esc_js($e['event_id']);
            $params   = wp_json_encode($e['params']);
            if (!is_string($params)) {
                $params = '{}';
            }
            $lines[] = "fbq('track', '{$name}', {$params}, { eventID: '{$event_id}' });";
        }

        return implode("\n        ", $lines);
    }

    /**
     * Generates a deterministic event_id for a given (event_name, scope) pair
     * within a single page load. Deterministic hashing ensures Pixel and CAPI
     * arrive at the same event_id when they independently process the same event.
     *
     * @param string $event_name
     * @param string $scope
     *
     * @return string
     */
    private function event_id_for(string $event_name, string $scope = ''): string
    {
        // Per-request seed ensures PageView on two tabs produces different ids.
        $seed = $this->request_seed();

        return substr(hash('sha256', $event_name . '|' . $scope . '|' . $seed), 0, 32);
    }

    /**
     * Stable seed per-request so that multiple event_id() calls within the same
     * render return consistent values.
     *
     * @return string
     */
    private function request_seed(): string
    {
        static $seed = null;
        if ($seed === null) {
            $seed = bin2hex(random_bytes(16));
        }
        return $seed;
    }

    /**
     * Returns a session-ish identifier for per-visitor transient keys.
     * Uses WC session customer id when available, falls back to _fbp cookie.
     *
     * @return string
     */
    private function session_key(): string
    {
        if (function_exists('WC') && WC()->session) {
            $id = (string) WC()->session->get_customer_id();
            if ($id !== '') {
                return 'wc_' . $id;
            }
        }

        $fbp = isset($_COOKIE['_fbp'])
            ? sanitize_text_field(wp_unslash($_COOKIE['_fbp']))
            : '';

        if ($fbp !== '') {
            return 'fbp_' . substr(preg_replace('/[^a-zA-Z0-9.]/', '', $fbp), 0, 40);
        }

        return '';
    }

    /**
     * @return bool
     */
    private function is_thank_you_page(): bool
    {
        return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received');
    }

    /**
     * @return int
     */
    private function detect_thank_you_order_id(): int
    {
        $order_id = (int) get_query_var('order-received');
        if ($order_id > 0) {
            return $order_id;
        }

        // Fallback: some themes re-route via query string.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public WC thank-you page, no nonce by design.
        if (!isset($_GET['order'])) {
            return 0;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same.
        $raw_order = sanitize_text_field(wp_unslash($_GET['order']));

        if (!is_numeric($raw_order)) {
            return 0;
        }

        return (int) $raw_order;
    }
}
