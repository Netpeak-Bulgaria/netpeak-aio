<?php

declare(strict_types=1);

namespace Netpeak\Settings;
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Canonical default settings tree.
 *
 * @since 0.1.0
 */
final class Defaults
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'ga4' => [
                'enabled'        => false,
                'property_id'    => '',
                'measurement_id' => '',
                'route_via_gtm'  => false,
            ],
            'gtm' => [
                'enabled'      => false,
                'container_id' => '',
            ],
            'gsc' => [
                'enabled'           => false,
                'site_url'          => '',
                'verification_file' => '',
            ],
            'oauth' => [
                'client_id'     => '',
                'client_secret' => '',
            ],
            'meta' => [
                'pixel_id' => '',
                'pixel' => [
                    'enabled' => false,
                ],
                'capi' => [
                    'enabled'   => false,
                    'test_code' => '',
                ],
            ],
        ];
    }
}
