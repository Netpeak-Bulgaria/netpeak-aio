document.addEventListener('alpine:init', () => {
    Alpine.data('settingsForm', () => ({
        activeTab: 'ga4',

        groups: [
            {
                label: 'Google',
                tabs: [
                    { key: 'ga4', label: 'Analytics 4' },
                    { key: 'gtm', label: 'Tag Manager' },
                    { key: 'gsc', label: 'Search Console' },
                ],
            },
            {
                label: 'Meta',
                tabs: [
                    { key: 'meta-pixel', label: 'Pixel' },
                    { key: 'meta-capi', label: 'Conversions API' },
                ],
            },
            {
                label: 'Authorization',
                tabs: [
                    { key: 'oauth', label: 'OAuth' },
                ],
            },
        ],

        get settings() {
            return Alpine.store('settings');
        },

        init() {
            this.settings.load();
        },

        save() {
            this.settings.save();
        },
    }));
});