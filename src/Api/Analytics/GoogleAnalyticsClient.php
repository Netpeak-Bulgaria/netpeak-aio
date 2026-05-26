<?php

declare(strict_types=1);

namespace Netpeak\Api\Analytics;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Api\OAuth\TokenRefresher;
use RuntimeException;

/**
 * Thin HTTP client for Google Analytics 4 Data API v1beta.
 *
 * @link https://developers.google.com/analytics/devguides/reporting/data/v1
 *
 * @since 0.1.0
 */
final class GoogleAnalyticsClient
{
    private const API_BASE = 'https://analyticsdata.googleapis.com/v1beta';
    private const TIMEOUT  = 20;

    /**
     * @param TokenRefresher $refresher
     */
    public function __construct(private readonly TokenRefresher $refresher)
    {
    }

    /**
     * POST /properties/{property_id}:runReport
     *
     * @param string               $property_id Numeric GA4 property ID (e.g. "347293851").
     * @param array<string, mixed> $query       Payload from QueryBuilder::build().
     *
     * @throws RuntimeException
     *
     * @return array<string, mixed>
     */
    public function run_report(string $property_id, array $query): array
    {
        $property_id = ltrim(trim($property_id), 'properties/');
        if ($property_id === '' || !ctype_digit($property_id)) {
            throw new RuntimeException('Invalid GA4 property ID.');
        }

        $endpoint = self::API_BASE . "/properties/{$property_id}:runReport";

        return $this->request('POST', $endpoint, $query);
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
            throw new RuntimeException('GA4 HTTP error: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300 || !is_array($data)) {
            $message = is_array($data) && !empty($data['error']['message'])
                ? (string) $data['error']['message']
                : "HTTP {$code}";
            throw new RuntimeException("GA4 API error: {$message}");
        }

        return $data;
    }
}
