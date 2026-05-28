<?php

declare(strict_types=1);


namespace Netpeak\Api\OAuth;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Support\Encryption;

/**
 * Persists Google OAuth tokens as a single encrypted WP option.
 *
 * @since 0.1.0
 */
final class TokenStorage
{
    private const OPTION_KEY  = 'netpeak_aio_google_oauth_tokens';
    private const SAFETY_SKEW = 60;

    /**
     * @param array{
     *     access_token: string,
     *     refresh_token?: string,
     *     expires_in?: int,
     *     scope?: string
     * } $tokens
     *
     * @return void
     */
    public function save(array $tokens): void
    {
        $existing_refresh = $this->get_refresh_token() ?? '';
        $refresh_token    = (string) ($tokens['refresh_token'] ?? $existing_refresh);

        $payload = [
            'access_token'  => Encryption::encrypt((string) ($tokens['access_token'] ?? '')),
            'refresh_token' => Encryption::encrypt($refresh_token),
            'expires_at'    => time() + max(0, (int) ($tokens['expires_in'] ?? 0)) - self::SAFETY_SKEW,
            'scope'         => (string) ($tokens['scope'] ?? ''),
        ];

        update_option(self::OPTION_KEY, $payload, false);
    }

    /**
     * @return string|null Null when expired or missing.
     */
    public function get_access_token(): ?string
    {
        $data = $this->read();
        if (($data['access_token'] ?? '') === '') {
            return null;
        }

        if ((int) ($data['expires_at'] ?? 0) < time()) {
            return null;
        }

        return $data['access_token'];
    }

    /**
     * @return string|null
     */
    public function get_refresh_token(): ?string
    {
        $data  = $this->read();
        $token = (string) ($data['refresh_token'] ?? '');

        return $token !== '' ? $token : null;
    }

    /**
     * @return bool
     */
    public function has_refresh_token(): bool
    {
        return $this->get_refresh_token() !== null;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        delete_option(self::OPTION_KEY);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: int, scope: string}
     */
    private function read(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'access_token'  => Encryption::decrypt((string) ($raw['access_token'] ?? '')),
            'refresh_token' => Encryption::decrypt((string) ($raw['refresh_token'] ?? '')),
            'expires_at'    => (int) ($raw['expires_at'] ?? 0),
            'scope'         => (string) ($raw['scope'] ?? ''),
        ];
    }
}
