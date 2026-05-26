<?php

declare(strict_types=1);


namespace Netpeak\Api\Meta;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Support\Encryption;

/**
 * Encrypted storage for Meta Conversions API Access Token.
 *
 * Kept separate from the main settings array because:
 *  - tokens are long (~200 chars) and don't belong in the settings blob
 *  - token rotation/disconnect needs its own lifecycle
 *  - tokens are never returned to the admin UI in plain text
 *
 * Mirrors the pattern of Api/OAuth/TokenStorage for Google credentials.
 *
 * @since 0.1.0
 */
final class TokenStorage
{
    private const OPTION_KEY = 'ntp_aio_meta_capi_token';

    /**
     * @param string $token Plain-text access token. Empty string clears storage.
     *
     * @return void
     */
    public function save(string $token): void
    {
        $token = trim($token);

        if ($token === '') {
            delete_option(self::OPTION_KEY);
            return;
        }

        $encrypted = Encryption::encrypt($token);
        if ($encrypted === '') {
            return;
        }

        update_option(self::OPTION_KEY, $encrypted, false);
    }

    /**
     * @return string Empty string when not set or decryption fails.
     */
    public function get(): string
    {
        $payload = (string) get_option(self::OPTION_KEY, '');
        if ($payload === '') {
            return '';
        }

        return Encryption::decrypt($payload);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->get() !== '';
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        delete_option(self::OPTION_KEY);
    }
}
