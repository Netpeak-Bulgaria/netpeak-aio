(function (window) {
    'use strict';

    const config = window.NetpeakAIO || {};

    async function request(path, options = {}) {
        const [rawPath, query] = String(path).split('?');
        const cleanPath = rawPath.replace(/^\//, '');

        let url = config.restUrl + cleanPath;
        if (query) {
            url += (url.includes('?') ? '&' : '?') + query;
        }

        const response = await fetch(url, {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce,
                ...(options.headers || {}),
            },
            body: options.body ? JSON.stringify(options.body) : undefined,
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error((data && data.error) || `HTTP ${response.status}`);
        }

        return data;
    }

    window.NetpeakAIO = Object.assign({}, config, { request });
})(window);
