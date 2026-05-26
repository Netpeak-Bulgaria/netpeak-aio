<?php

declare(strict_types=1);

namespace Netpeak\Integrations;
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Google Tag Manager: <head> snippet + <body> noscript iframe.
 *
 * @since 0.1.0
 */
final class TagManager extends AbstractIntegration
{
    /**
     * @return string
     */
    public function key(): string
    {
        return 'gtm';
    }

    /**
     * @return bool
     */
    public function is_configured(): bool
    {
        $id = (string) $this->settings->get('gtm.container_id', '');

        return preg_match('/^GTM-[A-Z0-9]+$/', $id) === 1;
    }

    /**
     * @return string
     */
    public function render_head(): string
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return '';
        }

        $id = esc_attr((string) $this->settings->get('gtm.container_id', ''));

        return <<<HTML
        <!-- Google Tag Manager (Analytics Netpeak AIO) -->
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{$id}');
        </script>
        HTML;
    }

    /**
     * @return string
     */
    public function render_body(): string
    {
        if (!$this->is_enabled() || !$this->is_configured()) {
            return '';
        }

        $id = esc_attr((string) $this->settings->get('gtm.container_id', ''));

        return <<<HTML
        <!-- Google Tag Manager noscript (Analytics Netpeak AIO) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$id}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        HTML;
    }
}
