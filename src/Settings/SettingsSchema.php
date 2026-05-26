<?php

declare(strict_types=1);

namespace Netpeak\Settings;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Support\Encryption;

/**
 * Sanitizes incoming settings payloads before persistence.
 *
 * Encrypted fields are listed in SettingsRepository::ENCRYPTED_PATHS and
 * wrapped with Encryption::encrypt() here at sanitize time.
 *
 * @since 0.1.0
 */
final class SettingsSchema
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        return [
            'ga4' => [
                'enabled'        => !empty($input['ga4']['enabled']),
                'measurement_id' => self::sanitize_ga4_id($input['ga4']['measurement_id'] ?? ''),
                'property_id'    => self::sanitize_ga4_property_id($input['ga4']['property_id'] ?? ''),
                'route_via_gtm'  => !empty($input['ga4']['route_via_gtm']),
            ],
            'gtm' => [
                'enabled'      => !empty($input['gtm']['enabled']),
                'container_id' => self::sanitize_gtm_id($input['gtm']['container_id'] ?? ''),
            ],
            'gsc' => [
                'enabled'           => !empty($input['gsc']['enabled']),
                'site_url'          => esc_url_raw((string) ($input['gsc']['site_url'] ?? '')),
                'verification_file' => self::sanitize_verification_file($input['gsc']['verification_file'] ?? ''),
            ],
            'oauth' => [
                'client_id'     => sanitize_text_field((string) ($input['oauth']['client_id'] ?? '')),
                'client_secret' => self::encrypt_text_field((string) ($input['oauth']['client_secret'] ?? '')),
            ],
            'meta' => [
                'pixel_id' => self::sanitize_meta_pixel_id($input['meta']['pixel_id'] ?? ''),
                'pixel' => [
                    'enabled' => !empty($input['meta']['pixel']['enabled']),
                ],
                'capi' => [
                    'enabled'   => !empty($input['meta']['capi']['enabled']),
                    'test_code' => self::sanitize_meta_test_code($input['meta']['capi']['test_code'] ?? ''),
                ],
            ],
        ];
    }

    /**
     * Sanitizes plaintext and returns its AES-256-GCM ciphertext.
     * Empty input stays empty.
     *
     * @param string $value
     *
     * @return string
     */
    private static function encrypt_text_field(string $value): string
    {
        $value = trim(sanitize_text_field($value));
        if ($value === '') {
            return '';
        }

        return Encryption::encrypt($value);
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private static function sanitize_ga4_id(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^G-[A-Z0-9]+$/', $value) === 1 ? $value : '';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private static function sanitize_verification_file(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/^google[a-f0-9]+\.html$/', $value) === 1 ? $value : '';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private static function sanitize_ga4_property_id(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d+$/', $value) === 1 ? $value : '';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private static function sanitize_gtm_id(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^GTM-[A-Z0-9]+$/', $value) === 1 ? $value : '';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private static function sanitize_meta_pixel_id(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{15,16}$/', $value) === 1 ? $value : '';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private static function sanitize_meta_test_code(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_-]{1,32}$/', $value) === 1 ? $value : '';
    }
}
