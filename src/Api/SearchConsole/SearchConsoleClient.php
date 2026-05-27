<?php

declare(strict_types=1);

namespace Netpeak\Api\SearchConsole;
if (!defined('ABSPATH')) {
    exit;
}


use Netpeak\Api\OAuth\TokenRefresher;
use RuntimeException;

/**
 * Thin HTTP client for Google Search Console Webmasters API v3.
 *
 * @link https://developers.google.com/webmaster-tools/about
 *
 * @since 0.1.0
 */
final class SearchConsoleClient
{
    private const API_BASE = 'https://searchconsole.googleapis.com/webmasters/v3';
    private const TIMEOUT  = 20;

    /**
     * @param TokenRefresher $refresher
     */
    public function __construct(private readonly TokenRefresher $refresher)
    {
    }

    /**
     * POST /sites/{siteUrl}/searchAnalytics/query
     *
     * @param string               $site_url
     * @param array<string, mixed> $query Payload from QueryBuilder::build().
     *
     * @throws RuntimeException
     *
     * @return array<string, mixed>
     */
    public function search_analytics(string $site_url, array $query): array
    {
        $endpoint = self::API_BASE . '/sites/' . rawurlencode($site_url) . '/searchAnalytics/query';

        return $this->request('POST', $endpoint, $query);
    }

    /**
     * GET /sites
     *
     * @throws RuntimeException
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_sites(): array
    {
        $data = $this->request('GET', self::API_BASE . '/sites');

        return is_array($data['siteEntry'] ?? null) ? $data['siteEntry'] : [];
    }

    /**
     * @param string                    $method
     * @param string                    $url
     * @param array<string, mixed>|null $body
     *
     * @throws RuntimeException
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $args = [
            'method'  => $method,
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->refresher->access_token(),
                'Accept'        => 'application/json',
            ],
        ];

        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new RuntimeException(esc_html('GSC HTTP error: ' . $response->get_error_message()));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300 || !is_array($data)) {
            $message = is_array($data) && !empty($data['error']['message'])
                ? (string) $data['error']['message']
                : "HTTP {$code}";
            throw new RuntimeException(esc_html("GSC API error: {$message}"));
        }

        return $data;
    }
}
