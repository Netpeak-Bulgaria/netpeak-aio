<?php

declare(strict_types=1);

namespace Netpeak\Admin;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Admin\Pages\ConnectPage;
use Netpeak\Admin\Pages\DashboardPage;
use Netpeak\Admin\Pages\SettingsPage;
use Netpeak\Container;

/**
 * Registers the top-level admin menu and all subpages.
 *
 * @since 0.1.0
 */
final class AdminMenu
{
    public const MENU_SLUG     = 'netpeak-aio';
    public const SETTINGS_SLUG = 'netpeak-aio-settings';
    public const CONNECT_SLUG  = 'netpeak-aio-connect';
    public const CAPABILITY    = 'manage_options';

    /**
     * @param Container $container
     */
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return void
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    /**
     * @return void
     */
    public function register_menu(): void
    {
        $dashboard = new DashboardPage($this->container);
        $settings  = new SettingsPage($this->container);
        $connect   = new ConnectPage($this->container);

        add_menu_page(
            'Netpeak AIO',
            'Netpeak AIO',
            self::CAPABILITY,
            self::MENU_SLUG,
            [$dashboard, 'render'],
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Dashboard',
            'Dashboard',
            self::CAPABILITY,
            self::MENU_SLUG,
            [$dashboard, 'render']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Settings',
            'Settings',
            self::CAPABILITY,
            self::SETTINGS_SLUG,
            [$settings, 'render']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Connect Google',
            'Connect Google',
            self::CAPABILITY,
            self::CONNECT_SLUG,
            [$connect, 'render']
        );
    }
}
