<?php

declare(strict_types=1);


namespace Netpeak\Rest\Controllers;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Api\Analytics\GoogleAnalyticsClient;
use Netpeak\Api\Analytics\QueryBuilder;
use Netpeak\Settings\SettingsRepository;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for GA4 Data API.
 *
 * @since 0.1.0
 */
final class AnalyticsController extends AbstractController
{
    private const DEFAULT_DAYS = 28;

    /**
     * @return void
     */
    public function register(): void
    {
        register_rest_route($this->namespace(), '/ga4/summary', [
            'methods'             => 'GET',
            'callback'            => [$this, 'summary'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args'                => [
                'days' => [
                    'type'              => 'integer',
                    'default'           => self::DEFAULT_DAYS,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route($this->namespace(), '/ga4/timeseries', [
            'methods'             => 'GET',
            'callback'            => [$this, 'timeseries'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args'                => [
                'days' => [
                    'type'              => 'integer',
                    'default'           => self::DEFAULT_DAYS,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route($this->namespace(), '/ga4/top-pages', [
            'methods'             => 'GET',
            'callback'            => [$this, 'top_pages'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args'                => [
                'limit' => [
                    'type'              => 'integer',
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Aggregated totals for current window + previous window (for delta).
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function summary(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $days    = $this->days($request);
            $client  = $this->client();
            $metrics = ['sessions', 'activeUsers', 'screenPageViews', 'engagementRate'];

            $current = $client->run_report(
                $this->property_id(),
                QueryBuilder::make()
                    ->metrics(...$metrics)
                    ->last_days($days)
                    ->build()
            );

            $previous = $client->run_report(
                $this->property_id(),
                QueryBuilder::make()
                    ->metrics(...$metrics)
                    ->previous_days($days)
                    ->build()
            );

            return new WP_REST_Response([
                'current'  => $this->extract_row($current),
                'previous' => $this->extract_row($previous),
                'days'     => $days,
            ], 200);
        } catch (Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Daily breakdown of sessions / users for chart rendering.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function timeseries(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $days = $this->days($request);

            $data = $this->client()->run_report(
                $this->property_id(),
                QueryBuilder::make()
                    ->metrics('sessions', 'activeUsers', 'screenPageViews')
                    ->dimensions('date')
                    ->last_days($days)
                    ->order_by_metric('sessions', false)
                    ->limit(1000)
                    ->build()
            );

            return new WP_REST_Response($data, 200);
        } catch (Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function top_pages(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $limit = (int) $request->get_param('limit');

            $data = $this->client()->run_report(
                $this->property_id(),
                QueryBuilder::make()
                    ->metrics('sessions', 'activeUsers', 'screenPageViews')
                    ->dimensions('pagePath')
                    ->last_days(self::DEFAULT_DAYS)
                    ->order_by_metric('sessions')
                    ->limit($limit)
                    ->build()
            );

            return new WP_REST_Response($data, 200);
        } catch (Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, float>
     */
    private function extract_row(array $response): array
    {
        $rows    = $response['rows'] ?? [];
        $headers = $response['metricHeaders'] ?? [];
        $result  = [];

        if (empty($rows) || !is_array($rows[0]['metricValues'] ?? null)) {
            foreach ($headers as $h) {
                $result[$h['name']] = 0.0;
            }
            return $result;
        }

        foreach ($headers as $index => $h) {
            $value = $rows[0]['metricValues'][$index]['value'] ?? '0';
            $result[$h['name']] = (float) $value;
        }

        return $result;
    }

    /**
     * @return GoogleAnalyticsClient
     */
    private function client(): GoogleAnalyticsClient
    {
        /** @var GoogleAnalyticsClient $client */
        $client = $this->container->get(GoogleAnalyticsClient::class);

        return $client;
    }

    /**
     * @return string
     */
    private function property_id(): string
    {
        /** @var SettingsRepository $settings */
        $settings = $this->container->get(SettingsRepository::class);

        return (string) $settings->get('ga4.property_id', '');
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return int
     */
    private function days(WP_REST_Request $request): int
    {
        $days = (int) $request->get_param('days');

        return $days > 0 ? $days : self::DEFAULT_DAYS;
    }
}
