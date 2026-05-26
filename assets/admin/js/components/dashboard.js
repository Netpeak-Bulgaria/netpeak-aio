document.addEventListener('alpine:init', () => {
    const chartHolder = { instance: null };

    Alpine.data('dashboard', () => ({
        days: 28,
        activeMetric: 'gsc.clicks',

        get gsc() { return Alpine.store('gsc'); },
        get ga4() { return Alpine.store('ga4'); },

        async init() {
            await this.reload();
            this.$watch('activeMetric', () => this.scheduleChart());
        },

        async setDays(days) {
            this.days = days;
            await this.reload();
        },

        async reload() {
            await Promise.all([
                this.gsc.loadSummary(this.days),
                this.gsc.loadTop(this.gsc.topDimension, 25, this.days),
                this.ga4.loadSummary(this.days),
                this.ga4.loadTimeseries(this.days),
            ]);
            this.scheduleChart();
        },

        scheduleChart() {
            this.$nextTick(() => {
                if (typeof Chart === 'undefined') {
                    setTimeout(() => this.scheduleChart(), 100);
                    return;
                }
                this.renderChart();
            });
        },

        renderChart() {
            const canvas = document.getElementById('aio-chart');
            if (!canvas) return;

            if (chartHolder.instance) {
                try { chartHolder.instance.destroy(); } catch (e) {}
                chartHolder.instance = null;
            }

            const config = this.chartConfig();
            if (!config) return;

            chartHolder.instance = new Chart(canvas, config);
        },

        formatNumber(value) {
            return new Intl.NumberFormat().format(Math.round(value || 0));
        },

        formatPercent(value) {
            return `${(value || 0).toFixed(2)}%`;
        },

        formatPosition(value) {
            return (value || 0).toFixed(1);
        },

        formatDate(raw) {
            if (!raw || raw.length !== 8) return raw;
            return `${raw.slice(0, 4)}-${raw.slice(4, 6)}-${raw.slice(6, 8)}`;
        },

        delta(current, previous) {
            if (!previous || previous === 0) {
                return { value: 0, direction: 'flat', display: '—' };
            }
            const pct = ((current - previous) / previous) * 100;
            return {
                value: pct,
                direction: pct > 0.1 ? 'up' : pct < -0.1 ? 'down' : 'flat',
                display: `${pct >= 0 ? '+' : ''}${pct.toFixed(1)}%`,
            };
        },

        metricLabel(key) {
            const labels = {
                'gsc.clicks':          'GSC Clicks',
                'gsc.impressions':     'GSC Impressions',
                'gsc.ctr':             'GSC CTR (%)',
                'gsc.position':        'GSC Avg. Position',
                'ga4.sessions':        'GA4 Sessions',
                'ga4.activeUsers':     'GA4 Users',
                'ga4.screenPageViews': 'GA4 Page views',
            };
            return labels[key] || key;
        },

        chartConfig() {
            const [source, metric] = this.activeMetric.split('.');

            if (source === 'gsc') {
                const rows = this.gsc.series();
                if (rows.length === 0) return null;

                return {
                    type: 'line',
                    data: {
                        labels: rows.map(r => r.keys && r.keys[0]),
                        datasets: [{
                            label: this.metricLabel(this.activeMetric),
                            data: rows.map(r => {
                                if (metric === 'ctr') return (r.ctr || 0) * 100;
                                return r[metric] || 0;
                            }),
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34,113,177,0.08)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                        }],
                    },
                    options: this.chartOptions(),
                };
            }

            if (source === 'ga4') {
                const rows = this.ga4.series();
                if (rows.length === 0) return null;

                return {
                    type: 'line',
                    data: {
                        labels: rows.map(r => this.formatDate(r.dimensionValues[0].value)),
                        datasets: [{
                            label: this.metricLabel(this.activeMetric),
                            data: rows.map(r => {
                                const headers = this.ga4.timeseries.metricHeaders || [];
                                const idx = headers.findIndex(h => h.name === metric);
                                if (idx < 0) return 0;
                                return parseFloat(r.metricValues[idx].value) || 0;
                            }),
                            borderColor: '#ea8038',
                            backgroundColor: 'rgba(234,128,56,0.08)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                        }],
                    },
                    options: this.chartOptions(),
                };
            }

            return null;
        },

        chartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                    y: { beginAtZero: true, grid: { color: '#f0f0f1' } },
                },
                interaction: { mode: 'nearest', intersect: false },
            };
        },
    }));
});
