document.addEventListener('alpine:init', () => {
    Alpine.store('settings', {
        data: null,
        loading: false,
        saving: false,
        saved: false,
        error: null,

        async load() {
            this.loading = true;
            this.error = null;
            try {
                this.data = await window.NetpeakAIO.request('settings');
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async save() {
            if (!this.data) return;
            this.saving = true;
            this.error = null;
            this.saved = false;
            try {
                const response = await window.NetpeakAIO.request('settings', {
                    method: 'POST',
                    body: this.data,
                });
                this.data = response.settings;
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 3000);
            } catch (e) {
                this.error = e.message;
            } finally {
                this.saving = false;
            }
        },
    });
});
