<?php

declare(strict_types=1);

namespace Netpeak\Rest\Controllers;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Api\SearchConsole\QueryBuilder;
use Netpeak\Api\SearchConsole\SearchConsoleClient;
use Netpeak\Settings\SettingsRepository;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints that proxy Google Search Console data to the admin UI.
 *
 * @since 0.1.0
 */
final class SearchConsoleController extends AbstractController
{
    private const DEFAULT_DAYS = 28;
    private const TOP_DEFAULT  = 25;

    /**
     * Whitelist of GSC dimensions we expose via /gsc/top.
     */
    private const ALLOWED_DIMENSIONS = ['query', 'page', 'country'];

    /**
     * @return void
     */
    public function register(): void
    {
        register_rest_route($this->namespace(), '/gsc/summary', [
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

        register_rest_route($this->namespace(), '/gsc/top', [
            'methods'             => 'GET',
            'callback'            => [$this, 'top'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args'                => [
                'dimension' => [
                    'type'              => 'string',
                    'default'           => 'query',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'limit' => [
                    'type'              => 'integer',
                    'default'           => self::TOP_DEFAULT,
                    'sanitize_callback' => 'absint',
                ],
                'days' => [
                    'type'              => 'integer',
                    'default'           => self::DEFAULT_DAYS,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route($this->namespace(), '/gsc/sites', [
            'methods'             => 'GET',
            'callback'            => [$this, 'sites'],
            'permission_callback' => [$this, 'check_admin_permissions'],
        ]);
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function summary(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $days   = $this->days($request);
            $client = $this->client();
            $site   = $this->site_url();

            $current = $client->search_analytics(
                $site,
                QueryBuilder::make()
                    ->last_days($days)
                    ->dimensions('date')
                    ->limit(1000)
                    ->build()
            );

            $previous = $client->search_analytics(
                $site,
                QueryBuilder::make()
                    ->previous_days($days)
                    ->dimensions('date')
                    ->limit(1000)
                    ->build()
            );

            return new WP_REST_Response([
                'current'  => $current,
                'previous' => $this->aggregate_totals($previous),
                'days'     => $days,
            ], 200);
        } catch (Throwable $e) {
            return $this->error_response($e);
        }
    }

    /**
     * Top rows for a single dimension (query/page/country).
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function top(WP_REST_Request $request): WP_REST_Response
    {
        $dimension = (string) $request->get_param('dimension');
        if (!in_array($dimension, self::ALLOWED_DIMENSIONS, true)) {
            return new WP_REST_Response([
                'error' => __('Unsupported dimension. Allowed: query, page, country.', 'netpeak-analytics-kit'),
            ], 400);
        }

        try {
            $limit = (int) $request->get_param('limit');
            $days  = $this->days($request);

            $query = QueryBuilder::make()
                ->last_days($days)
                ->dimensions($dimension)
                ->limit($limit)
                ->build();

            $data = $this->client()->search_analytics($this->site_url(), $query);

            // Tag response with dimension so frontend can render the right column header.
            $data['dimension'] = $dimension;

            return new WP_REST_Response($data, 200);
        } catch (Throwable $e) {
            return $this->error_response($e);
        }
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function sites(WP_REST_Request $request): WP_REST_Response
    {
        try {
            return new WP_REST_Response($this->client()->list_sites(), 200);
        } catch (Throwable $e) {
            return $this->error_response($e);
        }
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array{clicks: float, impressions: float, ctr: float, position: float}
     */
    private function aggregate_totals(array $response): array
    {
        $rows = is_array($response['rows'] ?? null) ? $response['rows'] : [];

        if (empty($rows)) {
            return ['clicks' => 0.0, 'impressions' => 0.0, 'ctr' => 0.0, 'position' => 0.0];
        }

        $clicks      = 0.0;
        $impressions = 0.0;
        $position    = 0.0;

        foreach ($rows as $row) {
            $clicks      += (float) ($row['clicks'] ?? 0);
            $impressions += (float) ($row['impressions'] ?? 0);
            $position    += (float) ($row['position'] ?? 0);
        }

        return [
            'clicks'      => $clicks,
            'impressions' => $impressions,
            'ctr'         => $impressions > 0 ? ($clicks / $impressions) * 100 : 0.0,
            'position'    => count($rows) > 0 ? $position / count($rows) : 0.0,
        ];
    }

    /**
     * @param Throwable $e
     *
     * @return WP_REST_Response
     */
    private function error_response(Throwable $e): WP_REST_Response
    {
        $raw     = $e->getMessage();
        $status  = 500;
        $message = $raw;

        if (stripos($raw, 'insufficient') !== false
            || stripos($raw, 'permissions') !== false
            || stripos($raw, 'not have') !== false
        ) {
            $status  = 403;
            $message = __('The connected Google account does not have access to this Search Console property.', 'netpeak-analytics-kit');
        } elseif (stripos($raw, 'not been used') !== false || stripos($raw, 'disabled') !== false) {
            $status  = 400;
            $message = __('Search Console API is not enabled in your Google Cloud project. Enable it and retry in 1–2 minutes.', 'netpeak-analytics-kit');
        } elseif (stripos($raw, 'invalid_grant') !== false || stripos($raw, 'token expired') !== false) {
            $status  = 401;
            $message = __('Google connection expired. Reconnect your account on the Connect Google page.', 'netpeak-analytics-kit');
        } elseif (stripos($raw, 'rate limit') !== false || stripos($raw, 'quota') !== false) {
            $status  = 429;
            $message = __('Google API rate limit reached. Try again in a minute.', 'netpeak-analytics-kit');
        }

        return new WP_REST_Response(['error' => $message, 'raw' => $raw], $status);
    }

    /**
     * @return SearchConsoleClient
     */
    private function client(): SearchConsoleClient
    {
        /** @var SearchConsoleClient $client */
        $client = $this->container->get(SearchConsoleClient::class);

        return $client;
    }

    /**
     * @return string
     */
    private function site_url(): string
    {
        /** @var SettingsRepository $settings */
        $settings = $this->container->get(SettingsRepository::class);
        $site     = (string) $settings->get('gsc.site_url', '');

        return $site !== '' ? $site : home_url('/');
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
