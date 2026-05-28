<?php
/**
 * @var array<string,mixed> $data
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Labels consumed by Alpine <template> blocks.
$gsc_cards = [
    ['key' => 'clicks',      'label' => esc_html__('Clicks', 'netpeak-analytics-kit'),       'format' => 'number'],
    ['key' => 'impressions', 'label' => esc_html__('Impressions', 'netpeak-analytics-kit'),  'format' => 'number'],
    ['key' => 'ctr',         'label' => esc_html__('CTR', 'netpeak-analytics-kit'),          'format' => 'percent'],
    ['key' => 'position',    'label' => esc_html__('Avg. position', 'netpeak-analytics-kit'),'format' => 'position'],
];

$ga4_cards = [
    ['key' => 'sessions',        'label' => esc_html__('Sessions', 'netpeak-analytics-kit'),        'format' => 'number'],
    ['key' => 'activeUsers',     'label' => esc_html__('Active users', 'netpeak-analytics-kit'),    'format' => 'number'],
    ['key' => 'screenPageViews', 'label' => esc_html__('Page views', 'netpeak-analytics-kit'),      'format' => 'number'],
    ['key' => 'engagementRate',  'label' => esc_html__('Engagement rate', 'netpeak-analytics-kit'), 'format' => 'rate'],
];

$metric_options = [
    ['key' => 'gsc.clicks',          'label' => esc_html__('GSC Clicks', 'netpeak-analytics-kit')],
    ['key' => 'gsc.impressions',     'label' => esc_html__('GSC Impressions', 'netpeak-analytics-kit')],
    ['key' => 'gsc.ctr',             'label' => esc_html__('GSC CTR', 'netpeak-analytics-kit')],
    ['key' => 'ga4.sessions',        'label' => esc_html__('GA4 Sessions', 'netpeak-analytics-kit')],
    ['key' => 'ga4.activeUsers',     'label' => esc_html__('GA4 Users', 'netpeak-analytics-kit')],
    ['key' => 'ga4.screenPageViews', 'label' => esc_html__('GA4 Views', 'netpeak-analytics-kit')],
];

$top_tabs = [
    ['key' => 'query',   'label' => esc_html__('Top queries', 'netpeak-analytics-kit'),   'header' => esc_html__('Query', 'netpeak-analytics-kit')],
    ['key' => 'page',    'label' => esc_html__('Top pages', 'netpeak-analytics-kit'),     'header' => esc_html__('Page', 'netpeak-analytics-kit')],
    ['key' => 'country', 'label' => esc_html__('Top countries', 'netpeak-analytics-kit'), 'header' => esc_html__('Country', 'netpeak-analytics-kit')],
];

$range_options = [7, 28, 90];

$i18n = [
    'loading'        => esc_html__('Loading…', 'netpeak-analytics-kit'),
    'vs_prev'        => esc_html__('vs prev', 'netpeak-analytics-kit'),
    'search_console' => esc_html__('Search Console', 'netpeak-analytics-kit'),
    'analytics_4'    => esc_html__('Analytics 4', 'netpeak-analytics-kit'),
    'no_data'        => esc_html__('No data for this period.', 'netpeak-analytics-kit'),
];

// Reusable inline SVG spinner. Kept as a variable so we can drop it into
// overlay divs without cluttering the markup.
$spinner_svg = '<svg class="netpeak-analytics-kit__spinner" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
    . '<circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>'
    . '</svg>';
?>
<div x-data="dashboard" x-init="init()" class="netpeak-analytics-kit__dashboard">

    <template x-if="gsc.error || ga4.error">
        <div class="netpeak-analytics-kit__error">
            <span x-text="gsc.error || ga4.error"></span>
        </div>
    </template>

    <div class="netpeak-analytics-kit__toolbar">
        <div class="netpeak-analytics-kit__range">
            <template x-for="option in <?php echo wp_json_encode($range_options); ?>" :key="option">
                <button type="button"
                        class="netpeak-analytics-kit__range-btn"
                        :class="days === option ? 'netpeak-analytics-kit__range-btn--active' : ''"
                        @click="setDays(option)"
                        x-text="option + 'd'"></button>
            </template>
        </div>
        <div class="netpeak-analytics-kit__toolbar-right">
            <span x-show="gsc.loading || ga4.loading" class="netpeak-analytics-kit__loading-pill">
                <?php echo $spinner_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo esc_html($i18n['loading']); ?>
            </span>
        </div>
    </div>

    <div class="netpeak-analytics-kit__row-group" :class="gsc.loading ? 'is-loading' : ''">
        <h3 class="netpeak-analytics-kit__row-title">
            <span class="netpeak-analytics-kit__row-accent netpeak-analytics-kit__row-accent--gsc"></span>
            <?php echo esc_html($i18n['search_console']); ?>
        </h3>
        <div class="netpeak-analytics-kit__grid">
            <template x-for="card in <?php echo esc_attr(wp_json_encode($gsc_cards)); ?>" :key="card.key">
                <div class="netpeak-analytics-kit__card">
                    <p class="netpeak-analytics-kit__card-label" x-text="card.label"></p>
                    <p class="netpeak-analytics-kit__card-value"
                       x-text="card.format === 'number' ? formatNumber(gsc.totals()[card.key])
                             : card.format === 'percent' ? formatPercent(gsc.totals()[card.key])
                             : formatPosition(gsc.totals()[card.key])"></p>
                    <p class="netpeak-analytics-kit__card-delta"
                       :class="{
                           'netpeak-analytics-kit__card-delta--up':   delta(gsc.totals()[card.key], gsc.prev()[card.key]).direction === (card.key === 'position' ? 'down' : 'up'),
                           'netpeak-analytics-kit__card-delta--down': delta(gsc.totals()[card.key], gsc.prev()[card.key]).direction === (card.key === 'position' ? 'up' : 'down'),
                           'netpeak-analytics-kit__card-delta--flat': delta(gsc.totals()[card.key], gsc.prev()[card.key]).direction === 'flat',
                       }"
                       x-text="delta(gsc.totals()[card.key], gsc.prev()[card.key]).display + ' <?php echo esc_js($i18n['vs_prev']); ?>'"></p>
                </div>
            </template>
        </div>
        <div class="netpeak-analytics-kit__overlay" x-show="gsc.loading" x-cloak>
            <?php echo $spinner_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>

    <div class="netpeak-analytics-kit__row-group" :class="ga4.loading ? 'is-loading' : ''">
        <h3 class="netpeak-analytics-kit__row-title">
            <span class="netpeak-analytics-kit__row-accent netpeak-analytics-kit__row-accent--ga4"></span>
            <?php echo esc_html($i18n['analytics_4']); ?>
        </h3>
        <div class="netpeak-analytics-kit__grid">
            <template x-for="card in <?php echo esc_attr(wp_json_encode($ga4_cards)); ?>" :key="card.key">
                <div class="netpeak-analytics-kit__card">
                    <p class="netpeak-analytics-kit__card-label" x-text="card.label"></p>
                    <p class="netpeak-analytics-kit__card-value"
                       x-text="card.format === 'number' ? formatNumber(ga4.metric(card.key))
                             : formatPercent(ga4.metric(card.key) * 100)"></p>
                    <p class="netpeak-analytics-kit__card-delta"
                       :class="{
                           'netpeak-analytics-kit__card-delta--up':   delta(ga4.metric(card.key), ga4.metricPrev(card.key)).direction === 'up',
                           'netpeak-analytics-kit__card-delta--down': delta(ga4.metric(card.key), ga4.metricPrev(card.key)).direction === 'down',
                           'netpeak-analytics-kit__card-delta--flat': delta(ga4.metric(card.key), ga4.metricPrev(card.key)).direction === 'flat',
                       }"
                       x-text="delta(ga4.metric(card.key), ga4.metricPrev(card.key)).display + ' <?php echo esc_js($i18n['vs_prev']); ?>'"></p>
                </div>
            </template>
        </div>
        <div class="netpeak-analytics-kit__overlay" x-show="ga4.loading" x-cloak>
            <?php echo $spinner_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>

    <div class="netpeak-analytics-kit__chart-block" :class="gsc.loading || ga4.loading ? 'is-loading' : ''">
        <div class="netpeak-analytics-kit__chart-header">
            <h3 class="netpeak-analytics-kit__chart-title"><?php esc_html_e('Timeseries', 'netpeak-analytics-kit'); ?></h3>
            <div class="netpeak-analytics-kit__metric-picker">
                <template x-for="opt in <?php echo esc_attr(wp_json_encode($metric_options)); ?>" :key="opt.key">
                    <button type="button"
                            class="netpeak-analytics-kit__metric-btn"
                            :class="activeMetric === opt.key ? 'netpeak-analytics-kit__metric-btn--active' : ''"
                            @click="activeMetric = opt.key"
                            x-text="opt.label"></button>
                </template>
            </div>
        </div>
        <div class="netpeak-analytics-kit__chart-canvas">
            <canvas id="aio-chart"></canvas>
        </div>
        <div class="netpeak-analytics-kit__overlay" x-show="gsc.loading || ga4.loading" x-cloak>
            <?php echo $spinner_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>

    <div class="netpeak-analytics-kit__table-block" :class="gsc.topLoading ? 'is-loading' : ''">
        <div class="netpeak-analytics-kit__top-tabs">
            <template x-for="tab in <?php echo esc_attr(wp_json_encode($top_tabs)); ?>" :key="tab.key">
                <button type="button"
                        class="netpeak-analytics-kit__top-tab"
                        :class="gsc.topDimension === tab.key ? 'netpeak-analytics-kit__top-tab--active' : ''"
                        :disabled="gsc.topLoading"
                        @click="gsc.setTopDimension(tab.key)"
                        x-text="tab.label"></button>
            </template>
        </div>

        <template x-if="gsc.top && Array.isArray(gsc.top.rows) && gsc.top.rows.length > 0">
            <table class="netpeak-analytics-kit__table">
                <thead>
                    <tr>
                        <th x-text="(<?php echo esc_attr(wp_json_encode($top_tabs)); ?>).find(t => t.key === gsc.topDimension).header"></th>
                        <th class="num"><?php esc_html_e('Clicks', 'netpeak-analytics-kit'); ?></th>
                        <th class="num"><?php esc_html_e('Impressions', 'netpeak-analytics-kit'); ?></th>
                        <th class="num"><?php esc_html_e('CTR', 'netpeak-analytics-kit'); ?></th>
                        <th class="num"><?php esc_html_e('Position', 'netpeak-analytics-kit'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in gsc.top.rows" :key="index">
                        <tr>
                            <td x-text="row.keys && row.keys[0]"></td>
                            <td class="num" x-text="formatNumber(row.clicks)"></td>
                            <td class="num" x-text="formatNumber(row.impressions)"></td>
                            <td class="num" x-text="formatPercent((row.ctr || 0) * 100)"></td>
                            <td class="num" x-text="formatPosition(row.position)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>

        <template x-if="gsc.top && Array.isArray(gsc.top.rows) && gsc.top.rows.length === 0 && !gsc.topLoading">
            <p class="netpeak-analytics-kit__empty"><?php echo esc_html($i18n['no_data']); ?></p>
        </template>

        <div class="netpeak-analytics-kit__overlay" x-show="gsc.topLoading" x-cloak>
            <?php echo $spinner_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>

</div>
