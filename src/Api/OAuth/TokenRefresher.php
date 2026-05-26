<?php

declare(strict_types=1);


namespace Netpeak\Api\OAuth;
if (!defined('ABSPATH')) {
    exit;
}

use RuntimeException;

/**
 * Returns a valid access token, refreshing it transparently when expired.
 *
 * @since 0.1.0
 */
final class TokenRefresher
{
    /**
     * @param GoogleOAuthClient $client
     * @param TokenStorage      $storage
     */
    public function __construct(
        private readonly GoogleOAuthClient $client,
        private readonly TokenStorage $storage,
    ) {
    }

    /**
     * @throws RuntimeException When no refresh token is available.
     *
     * @return string
     */
    public function access_token(): string
    {
        $cached = $this->storage->get_access_token();
        if ($cached !== null) {
            return $cached;
        }

        $refresh_token = $this->storage->get_refresh_token();
        if ($refresh_token === null) {
            throw new RuntimeException('Google account is not connected.');
        }

        $fresh = $this->client->refresh_access_token($refresh_token);
        $this->storage->save($fresh);

        return (string) $fresh['access_token'];
    }
}
