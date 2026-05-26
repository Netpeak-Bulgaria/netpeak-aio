<?php

declare(strict_types=1);


namespace Netpeak\Api\SearchConsole;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fluent builder for the Search Console searchAnalytics.query payload.
 *
 * @link https://developers.google.com/webmaster-tools/v1/searchanalytics/query
 *
 * @since 0.1.0
 */
final class QueryBuilder
{
    private const DATA_DELAY_DAYS = 3;
    private const MAX_ROWS        = 25000;

    /**
     * @var string[]
     */
    private array $dimensions = [];

    private ?string $start_date = null;
    private ?string $end_date   = null;
    private int $row_limit      = 1000;
    private int $start_row      = 0;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $filter_groups = [];

    /**
     * @return self
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * @param string ...$dimensions e.g. 'query', 'page', 'country', 'device', 'date'
     *
     * @return self
     */
    public function dimensions(string ...$dimensions): self
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    /**
     * @param string $start YYYY-MM-DD
     * @param string $end   YYYY-MM-DD
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
     * Shortcut: last N days ending at GSC's data availability horizon.
     *
     * @param int $days
     *
     * @return self
     */
    public function last_days(int $days): self
    {
        $end   = gmdate('Y-m-d', strtotime('-' . self::DATA_DELAY_DAYS . ' days'));
        $start = gmdate('Y-m-d', strtotime('-' . ($days + self::DATA_DELAY_DAYS) . ' days'));

        return $this->date_range($start, $end);
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
        $end   = gmdate('Y-m-d', strtotime('-' . (self::DATA_DELAY_DAYS + $days) . ' days'));
        $start = gmdate('Y-m-d', strtotime('-' . (self::DATA_DELAY_DAYS + $days * 2) . ' days'));

        return $this->date_range($start, $end);
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
        $this->start_row = max(0, $offset);

        return $this;
    }

    /**
     * @param string $dimension
     * @param string $operator  'equals' | 'contains' | 'notContains' | 'notEquals' | 'includingRegex' | 'excludingRegex'
     * @param string $expression
     *
     * @return self
     */
    public function filter(string $dimension, string $operator, string $expression): self
    {
        $this->filter_groups[] = [
            'filters' => [[
                'dimension'  => $dimension,
                'operator'   => $operator,
                'expression' => $expression,
            ]],
        ];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $payload = [
            'startDate'  => $this->start_date ?? gmdate('Y-m-d', strtotime('-30 days')),
            'endDate'    => $this->end_date ?? gmdate('Y-m-d', strtotime('-3 days')),
            'dimensions' => $this->dimensions,
            'rowLimit'   => $this->row_limit,
            'startRow'   => $this->start_row,
        ];

        if (!empty($this->filter_groups)) {
            $payload['dimensionFilterGroups'] = $this->filter_groups;
        }

        return $payload;
    }
}
