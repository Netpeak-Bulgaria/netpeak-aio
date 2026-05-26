<?php

declare(strict_types=1);

namespace Netpeak\Api\OAuth;
if (!defined('ABSPATH')) {
    exit;
}


use RuntimeException;

/**
 * Google OAuth 2.0 client (authorization code flow).
 *
 * @since 0.1.0
 */
final class GoogleOAuthClient
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * @var string[]
     */
    private const SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    /**
     * @param string $client_id
     * @param string $client_secret
     */
    public function __construct(
        private readonly string $client_id,
        private readonly string $client_secret,
    ) {
    }

    /**
     * @param string $state        CSRF token kept by the caller until callback.
     * @param string $redirect_uri Must match the one registered in Google Cloud Console.
     *
     * @return string
     */
    public function build_authorization_url(string $state, string $redirect_uri): string
    {
        $params = [
            'client_id'              => $this->client_id,
            'redirect_uri'           => $redirect_uri,
            'response_type'          => 'code',
            'scope'                  => implode(' ', self::SCOPES),
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'include_granted_scopes' => 'true',
            'state'                  => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchanges an authorization code for access + refresh tokens.
     *
     * @param string $code
     * @param string $redirect_uri
     *
     * @throws RuntimeException On HTTP or API error.
     *
     * @return array{access_token: string, refresh_token?: string, expires_in: int, scope?: string, token_type: string}
     */
    public function exchange_code(string $code, string $redirect_uri): array
    {
        return $this->request_token([
            'code'          => $code,
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]);
    }

    /**
     * Refreshes an access token using the refresh token.
     *
     * @param string $refresh_token
     *
     * @throws RuntimeException On HTTP or API error.
     *
     * @return array{access_token: string, expires_in: int, scope?: string, token_type: string}
     */
    public function refresh_access_token(string $refresh_token): array
    {
        return $this->request_token([
            'refresh_token' => $refresh_token,
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type'    => 'refresh_token',
        ]);
    }

    /**
     * @param array<string, string> $body
     *
     * @throws RuntimeException
     *
     * @return array<string, mixed>
     */
    private function request_token(array $body): array
    {
        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('OAuth HTTP error: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code !== 200 || !is_array($data) || empty($data['access_token'])) {
            $message = is_array($data) && !empty($data['error_description'])
                ? (string) $data['error_description']
                : "HTTP {$code}";
            throw new RuntimeException("OAuth token error: {$message}");
        }

        return $data;
    }
}
