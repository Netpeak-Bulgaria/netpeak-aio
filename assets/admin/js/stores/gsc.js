document.addEventListener('alpine:init', () => {
    Alpine.store('gsc', {
        current: null,
        previous: null,
        top: null,
        topDimension: 'query',
        loading: false,
        topLoading: false,
        error: null,
        days: 28,

        async loadSummary(days = 28) {
            this.loading = true;
            this.error = null;
            this.days = days;
            try {
                const response = await window.NetpeakAIO.request(`gsc/summary?days=${days}`);
                this.current = response.current || null;
                this.previous = response.previous || null;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async loadTop(dimension = 'query', limit = 25, days = 28) {
            this.topLoading = true;
            this.topDimension = dimension;
            try {
                this.top = await window.NetpeakAIO.request(
                    `gsc/top?dimension=${dimension}&limit=${limit}&days=${days}`
                );
            } catch (e) {
                this.error = e.message;
                this.top = { rows: [] };
            } finally {
                this.topLoading = false;
            }
        },

        setTopDimension(dimension) {
            this.loadTop(dimension, 25, this.days);
        },

        totals() {
            if (!this.current || !Array.isArray(this.current.rows) || this.current.rows.length === 0) {
                return { clicks: 0, impressions: 0, ctr: 0, position: 0 };
            }
            const rows = this.current.rows;
            const clicks = rows.reduce((s, r) => s + (r.clicks || 0), 0);
            const impressions = rows.reduce((s, r) => s + (r.impressions || 0), 0);
            const ctr = impressions > 0 ? (clicks / impressions) * 100 : 0;
            const position = rows.reduce((s, r) => s + (r.position || 0), 0) / rows.length;
            return { clicks, impressions, ctr, position };
        },

        prev() {
            return this.previous || { clicks: 0, impressions: 0, ctr: 0, position: 0 };
        },

        series() {
            if (!this.current || !Array.isArray(this.current.rows)) return [];
            return [...this.current.rows].sort((a, b) => {
                return (a.keys && a.keys[0] || '').localeCompare(b.keys && b.keys[0] || '');
            });
        },
    });
});
