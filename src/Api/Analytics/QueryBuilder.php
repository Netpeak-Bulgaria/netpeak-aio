<?php

declare(strict_types=1);

namespace Netpeak\Api\Analytics;
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Fluent builder for the GA4 Data API runReport payload.
 *
 * @link https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/runReport
 *
 * @since 0.1.0
 */
final class QueryBuilder
{
    private const MAX_ROWS = 10000;

    /**
     * @var array<int, array{name: string}>
     */
    private array $metrics = [];

    /**
     * @var array<int, array{name: string}>
     */
    private array $dimensions = [];

    private string $start_date = '28daysAgo';
    private string $end_date   = 'today';
    private int $row_limit     = 100;
    private int $offset        = 0;

    /**
     * @var array<int, array{metric: array{metricName: string}, desc: bool}>
     */
    private array $order_bys = [];

    /**
     * @return self
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * @param string ...$names e.g. 'sessions', 'activeUsers', 'screenPageViews'
     *
     * @return self
     */
    public function metrics(string ...$names): self
    {
        $this->metrics = array_map(
            static fn (string $n): array => ['name' => $n],
            $names
        );

        return $this;
    }

    /**
     * @param string ...$names e.g. 'date', 'country', 'deviceCategory', 'pagePath'
     *
     * @return self
     */
    public function dimensions(string ...$names): self
    {
        $this->dimensions = array_map(
            static fn (string $n): array => ['name' => $n],
            $names
        );

        return $this;
    }

    /**
     * @param string $start YYYY-MM-DD or relative ('28daysAgo', 'yesterday').
     * @param string $end   YYYY-MM-DD or 'today'.
     *
     * @return self
     */
    public function date_range(string $start, string $end): self
    {
        $this->start_date = $start;
        $this->end_date   = $end;

        return $this;
    }

    /**
     * @param int $days
     *
     * @return self
     */
    public function last_days(int $days): self
    {
        return $this->date_range("{$days}daysAgo", 'today');
    }

    /**
     * Previous period of equal length ending right before the current window.
     *
     * @param int $days
     *
     * @return self
     */
    public function previous_days(int $days): self
    {
        $start = $days * 2;
        $end   = $days + 1;

        return $this->date_range("{$start}daysAgo", "{$end}daysAgo");
    }

    /**
     * @param int $rows
     * @param int $offset
     *
     * @return self
     */
    public function limit(int $rows, int $offset = 0): self
    {
        $this->row_limit = max(1, min(self::MAX_ROWS, $rows));
        $this->offset    = max(0, $offset);

        return $this;
    }

    /**
     * @param string $metric
     * @param bool   $desc
     *
     * @return self
     */
    public function order_by_metric(string $metric, bool $desc = true): self
    {
        $this->order_bys[] = [
            'metric' => ['metricName' => $metric],
            'desc'   => $desc,
        ];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $payload = [
            'dateRanges' => [[
                'startDate' => $this->start_date,
                'endDate'   => $this->end_date,
            ]],
            'metrics' => $this->metrics,
            'limit'   => $this->row_limit,
            'offset'  => $this->offset,
        ];

        if (!empty($this->dimensions)) {
            $payload['dimensions'] = $this->dimensions;
        }

        if (!empty($this->order_bys)) {
            $payload['orderBys'] = $this->order_bys;
        }

        return $payload;
    }
}
