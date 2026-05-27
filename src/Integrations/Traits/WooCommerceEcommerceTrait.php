<?php

declare(strict_types=1);

namespace Netpeak\Integrations\Traits;

use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Shared GA4 Enhanced Ecommerce payload builders.
 *
 * Used by both GoogleAnalytics (direct gtag.js) and TagManager (dataLayer push)
 * integrations to avoid duplicating product/cart/order serialization logic.
 *
 * GA4 spec: https://developers.google.com/analytics/devguides/collection/ga4/reference/events
 *
 * @since 0.1.0
 */
trait WooCommerceEcommerceTrait
{
    /**
     * Max items per view_item_list push — keeps dataLayer payload reasonable.
     */
    private int $list_max_items = 20;

    /**
     * @return string
     */
    protected function currency(): string
    {
        return function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR';
    }

    /**
     * Builds a single GA4 item payload from a WC product.
     *
     * @param WC_Product $product
     * @param int        $quantity
     *
     * @return array<string, mixed>
     */
    protected function build_item_payload(WC_Product $product, int $quantity = 1): array
    {
        $item = [
            'item_id'   => $product->get_sku() !== '' ? $product->get_sku() : (string) $product->get_id(),
            'item_name' => $product->get_name(),
            'price'     => (float) $product->get_price(),
            'quantity'  => max(1, $quantity),
        ];

        $categories = $this->collect_category_names($product);
        if (isset($categories[0])) $item['item_category']  = $categories[0];
        if (isset($categories[1])) $item['item_category2'] = $categories[1];
        if (isset($categories[2])) $item['item_category3'] = $categories[2];

        $brand = $this->collect_brand_name($product->get_id());
        if ($brand !== '') {
            $item['item_brand'] = $brand;
        }

        return $item;
    }

    /**
     * Builds items[] from the current WC cart.
     *
     * @param WC_Cart $cart
     *
     * @return array<int, array<string, mixed>>
     */
    protected function build_cart_items_payload(WC_Cart $cart): array
    {
        $items = [];
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if (!$product instanceof WC_Product) {
                continue;
            }
            $items[] = $this->build_item_payload($product, (int) ($cart_item['quantity'] ?? 1));
        }

        return $items;
    }

    /**
     * Builds items[] from a completed WC order.
     *
     * @param WC_Order $order
     *
     * @return array<int, array<string, mixed>>
     */
    protected function build_order_items_payload(WC_Order $order): array
    {
        $items = [];
        foreach ($order->get_items() as $line) {
            if (!$line instanceof WC_Order_Item_Product) {
                continue;
            }
            $product = $line->get_product();
            if (!$product instanceof WC_Product) {
                continue;
            }
            $items[] = $this->build_item_payload($product, (int) $line->get_quantity());
        }

        return $items;
    }

    /**
     * Builds items[] from a WP_Query of products (shop/category/tag archives).
     *
     * @param array<int, \WP_Post> $posts
     * @param string               $list_name
     *
     * @return array<int, array<string, mixed>>
     */
    protected function build_list_items_payload(array $posts, string $list_name): array
    {
        $items = [];
        $index = 1;

        foreach (array_slice($posts, 0, $this->list_max_items) as $post) {
            if (!function_exists('wc_get_product')) {
                break;
            }
            $product = wc_get_product($post->ID);
            if (!$product instanceof WC_Product) {
                continue;
            }

            $item                   = $this->build_item_payload($product);
            $item['index']          = $index++;
            $item['item_list_name'] = $list_name;
            $items[]                = $item;
        }

        return $items;
    }

    /**
     * Returns a human-readable label for the current list context.
     *
     * @return string
     */
    protected function current_list_name(): string
    {
        if (function_exists('is_search') && is_search()) {
            return 'Search results';
        }

        if (function_exists('is_product_category') && is_product_category()) {
            $term = get_queried_object();
            return $term && isset($term->name) ? (string) $term->name : 'Category';
        }

        if (function_exists('is_product_tag') && is_product_tag()) {
            $term = get_queried_object();
            return $term && isset($term->name) ? 'Tag: ' . (string) $term->name : 'Tag';
        }

        return 'Shop';
    }

    /**
     * @param WC_Product $product
     *
     * @return list<string>
     */
    private function collect_category_names(WC_Product $product): array
    {
        $names = [];
        foreach ($product->get_category_ids() as $cat_id) {
            $term = get_term((int) $cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $names[] = (string) $term->name;
            }
        }

        return $names;
    }

    /**
     * @param int $product_id
     *
     * @return string
     */
    private function collect_brand_name(int $product_id): string
    {
        $terms = get_the_terms($product_id, 'product_brand');
        if (is_array($terms) && !empty($terms) && isset($terms[0]->name)) {
            return (string) $terms[0]->name;
        }

        return '';
    }
}
