<?php

declare(strict_types=1);

namespace Netpeak\Integrations;
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Google Analytics 4 via gtag.js.
 *
 * Emits nothing when GA4 is routed through GTM — in that case GTM owns the tag.
 *
 * @since 0.1.0
 */
final class GoogleAnalytics extends AbstractIntegration
{
    /**
     * @return string
     */
    public function key(): string
    {
        return 'ga4';
    }

    /**
     * @return bool
     */
    public function is_configured(): bool
    {
        $id = (string) $this->settings->get('ga4.measurement_id', '');

        return preg_match('/^G-[A-Z0-9]+$/', $id) === 1;
    }

    /**
     * @return string
     */
    public function render_head(): string
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return '';
        }

        if ((bool) $this->settings->get('ga4.route_via_gtm', false)) {
            return '';
        }

        $id = esc_attr((string) $this->settings->get('ga4.measurement_id', ''));

        return <<<HTML
        <!-- Google Analytics 4 (Analytics Netpeak AIO) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{$id}');
        </script>
        HTML;
    }
}
