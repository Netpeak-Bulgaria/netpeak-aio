document.addEventListener('alpine:init', () => {
    Alpine.store('ga4', {
        current: null,
        previous: null,
        timeseries: null,
        loading: false,
        error: null,
        days: 28,

        async loadSummary(days = 28) {
            this.loading = true;
            this.error = null;
            this.days = days;
            try {
                const response = await window.NetpeakAIO.request(`ga4/summary?days=${days}`);
                this.current = response.current || null;
                this.previous = response.previous || null;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async loadTimeseries(days = 28) {
            try {
                this.timeseries = await window.NetpeakAIO.request(`ga4/timeseries?days=${days}`);
            } catch (e) {
                this.error = e.message;
            }
        },

        metric(name) {
            return (this.current && this.current[name]) || 0;
        },

        metricPrev(name) {
            return (this.previous && this.previous[name]) || 0;
        },

        series() {
            if (!this.timeseries || !Array.isArray(this.timeseries.rows)) return [];
            return [...this.timeseries.rows].sort((a, b) => {
                const dateA = a.dimensionValues && a.dimensionValues[0] && a.dimensionValues[0].value || '';
                const dateB = b.dimensionValues && b.dimensionValues[0] && b.dimensionValues[0].value || '';
                return dateA.localeCompare(dateB);
            });
        },
    });
});