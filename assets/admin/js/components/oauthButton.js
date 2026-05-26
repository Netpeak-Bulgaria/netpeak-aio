document.addEventListener('alpine:init', () => {
    Alpine.data('oauthButton', (initialConnected = false) => ({
        connected: Boolean(initialConnected),
        loading: false,
        error: null,

        async connect() {
            this.loading = true;
            this.error = null;
            try {
                const response = await window.NetpeakAIO.request('oauth/start');
                window.location.href = response.url;
            } catch (e) {
                this.error = e.message;
                this.loading = false;
            }
        },

        async disconnect() {
            if (!window.confirm('Disconnect Google account?')) return;
            this.loading = true;
            this.error = null;
            try {
                await window.NetpeakAIO.request('oauth/disconnect', { method: 'POST' });
                this.connected = false;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },
    }));
});
