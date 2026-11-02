<?php

declare(strict_types=1);

namespace Netpeak\Support;
if (!defined('ABSPATH')) {
    exit;
}

use WC_Product;

/**
 * Single place where the public event filters are applied, so every integration behaves the same.
 *
 * Channels: 'ga4', 'gtm', 'meta'. Event names are the native names of the channel
 * (view_item, add_to_cart, purchase for ga4 and gtm. PageView, ViewContent, AddToCart, InitiateCheckout, Purchase, Search for meta).
 *
 * Filters:
 *  - netpeak_aio_event_enabled (bool $enabled, string $event, string $channel, array $payload)
 *  - netpeak_aio_event_payload (array $payload, string $event, string $channel). For gtm the payload is the `ecommerce` object.
 *  - netpeak_aio_item_id       (string $id, WC_Product $product, string $channel)
 *  - netpeak_aio_item          (array $item, WC_Product $product, string $channel). ga4 and gtm items.
 *
 * @since 0.1.2
 */
final class EventFilter
{
    /**
     * @param string               $channel
     * @param string               $event
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null Filtered payload, or null when the event is disabled.
     */
    public static function apply(string $channel, string $event, array $payload): ?array
    {
        $enabled = (bool) apply_filters('netpeak_aio_event_enabled', true, $event, $channel, $payload);
        if (!$enabled) {
            return null;
        }

        $filtered = apply_filters('netpeak_aio_event_payload', $payload, $event, $channel);

        return is_array($filtered) ? $filtered : null;
    }

    /**
     * Enabled check for code paths that have no payload yet (for example the browser-side add_to_cart listener).
     *
     * @param string $channel
     * @param string $event
     *
     * @return bool
     */
    public static function is_enabled(string $channel, string $event): bool
    {
        return (bool) apply_filters('netpeak_aio_event_enabled', true, $event, $channel, []);
    }

    /**
     * @param string     $id
     * @param WC_Product $product
     * @param string     $channel
     *
     * @return string
     */
    public static function item_id(string $id, WC_Product $product, string $channel): string
    {
        return (string) apply_filters('netpeak_aio_item_id', $id, $product, $channel);
    }

    /**
     * @param array<string, mixed> $item
     * @param WC_Product           $product
     * @param string               $channel
     *
     * @return array<string, mixed>
     */
    public static function item(array $item, WC_Product $product, string $channel): array
    {
        $filtered = apply_filters('netpeak_aio_item', $item, $product, $channel);

        return is_array($filtered) ? $filtered : $item;
    }
}
