<?php

declare(strict_types=1);

namespace Netpeak\Admin\Pages;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GSC metrics dashboard page.
 *
 * @since 0.1.0
 */
final class DashboardPage extends AbstractPage
{
    /**
     * @return string
     */
    protected function title(): string
    {
        return 'Netpeak AIO — Dashboard';
    }

    /**
     * @return string
     */
    protected function view(): string
    {
        return 'dashboard';
    }
}
