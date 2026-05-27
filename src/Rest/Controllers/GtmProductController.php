<?php

declare(strict_types=1);

namespace Netpeak\Rest\Controllers;
if (!defined('ABSPATH')) {
    exit;
}

use WC_Product;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves lightweight product payloads for the AJAX add_to_cart listener
 * used by both GoogleAnalytics (gtag) and TagManager (dataLayer) integrations.
 *
 * Nonce-protected, public read (WooCommerce product data is already public).
 *
 * @since 0.1.0
 */
final class GtmProductController extends AbstractController
{
    /**
     * @return void
     */
    public function register(): void
    {
        register_rest_route($this->namespace(), '/gtm/product', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_product'],
            'permission_callback' => [$this, 'check_nonce'],
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * @return bool
     */
    public function check_nonce(): bool
    {
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE']))
            : '';

        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_product(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($id <= 0 || !function_exists('wc_get_product')) {
            return new WP_REST_Response(['error' => 'invalid_id'], 400);
        }

        $product = wc_get_product($id);
        if (!$product instanceof WC_Product) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        $category_name = '';
        $cat_ids       = $product->get_category_ids();
        if (!empty($cat_ids)) {
            $term = get_term((int) $cat_ids[0], 'product_cat');
            if ($term && !is_wp_error($term)) {
                $category_name = (string) $term->name;
            }
        }

        $brand_name  = '';
        $brand_terms = get_the_terms($id, 'product_brand');
        if (is_array($brand_terms) && !empty($brand_terms) && isset($brand_terms[0]->name)) {
            $brand_name = (string) $brand_terms[0]->name;
        }

        $payload = [
            'item_id'       => $product->get_sku() !== '' ? $product->get_sku() : (string) $product->get_id(),
            'item_name'     => $product->get_name(),
            'price'         => (float) $product->get_price(),
            'item_category' => $category_name,
            'item_brand'    => $brand_name,
        ];

        return new WP_REST_Response($payload, 200);
    }
}
