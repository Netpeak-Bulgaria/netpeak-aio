<?php

declare(strict_types=1);

namespace Netpeak\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta Pixel (client-side) integration.
 *
 * Events emitted:
 *  - PageView         — every non-admin page
 *  - ViewContent      — single product pages
 *  - Search           — search results pages
 *  - AddToCart        — on woocommerce_add_to_cart (queued in WC session)
 *  - InitiateCheckout — checkout page
 *  - Purchase         — thank-you page
 *
 * Each event carries a unique event_id so CAPI can deduplicate by
 * (event_name, event_id).
 *
 * @since 0.1.0
 */
final class MetaPixel extends AbstractIntegration
{
    /**
     * Anchor handle for the inline Pixel loader and events.
     */
    private const SCRIPT_HANDLE = 'ntp-aio-meta-pixel';

    /**
     * WC session key for events queued during a previous request.
     */
    private const PENDING_SESSION_KEY = 'ntp_aio_meta_pending_events';

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
     * @return void
     */
    public function register(): void
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_add_to_cart', [$this, 'capture_add_to_cart'], 10, 4);
    }

    /**
     * Enqueues the Pixel loader and attaches all inline scripts:
     * loader IIFE, init, PageView, queued + contextual events.
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        if (is_admin()) {
            return;
        }

        $pixel_id = (string) $this->settings->get('meta.pixel_id', '');

        wp_register_script(self::SCRIPT_HANDLE, '', [], null, false);
        wp_enqueue_script(self::SCRIPT_HANDLE);

        // Loader IIFE + init + base PageView.
        wp_add_inline_script(self::SCRIPT_HANDLE, $this->build_loader_js($pixel_id), 'after');

        // Queued events (e.g. AddToCart from a previous request) + page-contextual.
        $events = $this->build_events_for_current_request();
        $events_js = $this->build_events_js($events);
        if ($events_js !== '') {
            wp_add_inline_script(self::SCRIPT_HANDLE, $events_js, 'after');
        }
    }

    /**
     * <noscript> fallback for Pixel (PageView only). Cannot be enqueued.
     *
     * @return string
     */
    public function render_body(): string
    {
        if (!$this->is_enabled() || !$this->is_configured() || is_admin()) {
            return '';
        }

        $pixel_id = (string) $this->settings->get('meta.pixel_id', '');
        $src      = 'https://www.facebook.com/tr?id=' . rawurlencode($pixel_id) . '&ev=PageView&noscript=1';

        return sprintf(
            '<noscript><img height="1" width="1" style="display:none" src="%s" alt="" /></noscript>',
            esc_url($src)
        );
    }

    /**
     * @param string $pixel_id
     *
     * @return string
     */
    private function build_loader_js(string $pixel_id): string
    {
        $view_event_id = $this->event_id_for('PageView');

        return sprintf(
            '!function(f,b,e,v,n,t,s)'
            . '{if(f.fbq)return;n=f.fbq=function(){n.callMethod?'
            . 'n.callMethod.apply(n,arguments):n.queue.push(arguments)};'
            . "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';"
            . 'n.queue=[];t=b.createElement(e);t.async=!0;'
            . 't.src=v;s=b.getElementsByTagName(e)[0];'
            . "s.parentNode.insertBefore(t,s)}(window,document,'script',"
            . "'https://connect.facebook.net/en_US/fbevents.js');"
            . 'fbq(%s,%s);'
            . 'fbq(%s,%s,{},{eventID:%s});',
            wp_json_encode('init'),
            wp_json_encode($pixel_id),
            wp_json_encode('track'),
            wp_json_encode('PageView'),
            wp_json_encode($view_event_id)
        );
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

        $pending   = $this->read_pending_events();
        $pending[] = $event;
        WC()->session->set(self::PENDING_SESSION_KEY, $pending);
    }

    /**
     * @return array<int, array{name:string, event_id:string, params:array<string,mixed>}>
     */
    private function read_pending_events(): array
    {
        if (!function_exists('WC') || !WC()->session) {
            return [];
        }

        $pending = WC()->session->get(self::PENDING_SESSION_KEY, []);

        return is_array($pending) ? $pending : [];
    }

    /**
     * @return array<int, array{name:string, event_id:string, params:array<string,mixed>}>
     */
    private function build_events_for_current_request(): array
    {
        $events = [];

        // Flush queued events from a previous request (AddToCart).
        $pending = $this->read_pending_events();
        if (!empty($pending)) {
            foreach ($pending as $e) {
                if (is_array($e) && isset($e['name'], $e['event_id'], $e['params'])) {
                    $events[] = $e;
                }
            }
            if (function_exists('WC') && WC()->session) {
                WC()->session->set(self::PENDING_SESSION_KEY, []);
            }
        }

        // Page-contextual events.
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
            'event_id' => $this->event_id_for('InitiateCheckout', (string) WC()->session->get_customer_id()),
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
     * Builds the fbq('track', ...) JS for all events.
     * Params are JSON-encoded via wp_json_encode (safe for inline script).
     *
     * @param array<int, array{name:string, event_id:string, params:array<string,mixed>}> $events
     *
     * @return string
     */
    private function build_events_js(array $events): string
    {
        if (empty($events)) {
            return '';
        }

        $lines = [];
        foreach ($events as $e) {
            $lines[] = sprintf(
                'fbq(%s,%s,%s,{eventID:%s});',
                wp_json_encode('track'),
                wp_json_encode($e['name']),
                wp_json_encode($e['params']),
                wp_json_encode($e['event_id'])
            );
        }

        return implode('', $lines);
    }

    /**
     * @param string $event_name
     * @param string $scope
     *
     * @return string
     */
    private function event_id_for(string $event_name, string $scope = ''): string
    {
        $seed = $this->request_seed();

        return substr(hash('sha256', $event_name . '|' . $scope . '|' . $seed), 0, 32);
    }

    /**
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
