<?php

declare(strict_types=1);


namespace Netpeak\Support;
if (!defined('ABSPATH')) {
    exit;
}
/**
 * AES-256-GCM encryption helper. Key is derived from WP auth keys.
 *
 * @since 0.1.0
 */
final class Encryption
{
    private const CIPHER   = 'aes-256-gcm';
    private const IV_LEN   = 12;
    private const TAG_LEN  = 16;

    /**
     * @param string $plain
     *
     * @return string Base64-encoded IV || TAG || CIPHERTEXT. Empty string on empty input.
     */
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        $iv  = random_bytes(self::IV_LEN);
        $tag = '';

        $cipher = openssl_encrypt(
            $plain,
            self::CIPHER,
            self::derive_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($cipher === false) {
            return '';
        }

        return base64_encode($iv . $tag . $cipher);
    }

    /**
     * @param string $payload Base64-encoded payload produced by encrypt().
     *
     * @return string Decrypted plaintext. Empty string on failure.
     */
    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < (self::IV_LEN + self::TAG_LEN + 1)) {
            return '';
        }

        $iv         = substr($raw, 0, self::IV_LEN);
        $tag        = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::derive_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plain === false ? '' : $plain;
    }

    /**
     * @return string 32-byte binary key.
     */
    private static function derive_key(): string
    {
        $source = (defined('AUTH_KEY') ? AUTH_KEY : '')
            . (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '');

        if ($source === '') {
            $source = wp_salt('auth');
        }

        return hash('sha256', 'google_aio|' . $source, true);
    }
}
