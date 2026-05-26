<?php

declare(strict_types=1);


namespace Netpeak\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GA4 / GTM / GSC configuration page.
 *
 * @since 0.1.0
 */
final class SettingsPage extends AbstractPage
{
    /**
     * @return string
     */
    protected function title(): string
    {
        return 'Netpeak AIO — Settings';
    }

    /**
     * @return string
     */
    protected function view(): string
    {
        return 'settings';
    }
}
