<?php

declare(strict_types=1);

namespace Netpeak\Support;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Rest\RestRouter;

/**
 * Enqueues Alpine.js, Chart.js and admin JS modules on plugin admin pages.
 *
 * @since 0.1.0
 */
final class Assets
{
    private const HANDLE_ALPINE = 'netpeak-aio-alpine';
    private const HANDLE_CHART  = 'netpeak-aio-chart';
    private const HANDLE_ADMIN  = 'netpeak-aio-admin';

    private const ALPINE_VER = '3.14.1';
    private const CHART_VER  = '4.4.4';

    /**
     * Admin JS modules. Each loads after the main app handle.
     * Order matters: stores must register before components consume them.
     *
     * @var array<string, string>
     */
    private const ADMIN_MODULES = [
        'store-settings'      => 'stores/settings.js',
        'store-gsc'           => 'stores/gsc.js',
        'store-ga4'           => 'stores/ga4.js',
        'component-settings'  => 'components/settingsForm.js',
        'component-dashboard' => 'components/dashboard.js',
        'component-oauth'     => 'components/oauthButton.js',
    ];

    private readonly string $base_url;
    private readonly string $base_path;

    public function __construct(string $plugin_file)
    {
        $this->base_url  = plugin_dir_url($plugin_file);
        $this->base_path = plugin_dir_path($plugin_file);
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
    }

    /**
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_admin(string $hook): void
    {
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        $this->enqueue_vendor();
        $this->enqueue_app();
        $this->enqueue_modules();
        $this->enqueue_styles();
    }

    private function is_plugin_page(string $hook): bool
    {
        return str_contains($hook, 'netpeak-aio');
    }

    private function enqueue_vendor(): void
    {
        wp_enqueue_script(
            self::HANDLE_CHART,
            $this->base_url . 'assets/admin/js/chart.min.js',
            [],
            self::CHART_VER,
            true
        );
    }

    private function enqueue_app(): void
    {
        wp_enqueue_script(
            self::HANDLE_ADMIN,
            $this->base_url . 'assets/admin/js/app.js',
            [self::HANDLE_CHART],
            NTP_AIO_VERSION,
            true
        );

        wp_add_inline_script(
            self::HANDLE_ADMIN,
            'window.NetpeakAIO=' . wp_json_encode($this->bootstrap_data()) . ';',
            'before'
        );
    }

    private function enqueue_modules(): void
    {
        $last_handle = self::HANDLE_ADMIN;

        foreach (self::ADMIN_MODULES as $suffix => $path) {
            $handle = self::HANDLE_ADMIN . '-' . $suffix;

            wp_enqueue_script(
                $handle,
                $this->base_url . 'assets/admin/js/' . $path,
                [self::HANDLE_ADMIN],
                NTP_AIO_VERSION,
                true
            );

            $last_handle = $handle;
        }

        wp_enqueue_script(
            self::HANDLE_ALPINE,
            $this->base_url . 'assets/admin/js/alpine.min.js',
            [$last_handle],
            self::ALPINE_VER,
            true
        );
    }

    private function enqueue_styles(): void
    {
        $css_path = $this->base_path . 'assets/admin/css/admin.css';

        if (!file_exists($css_path)) {
            return;
        }

        wp_enqueue_style(
            self::HANDLE_ADMIN,
            $this->base_url . 'assets/admin/css/admin.css',
            [],
            NTP_AIO_VERSION
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bootstrap_data(): array
    {
        return [
            'restUrl'  => esc_url_raw(rest_url(RestRouter::NAMESPACE . '/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url('admin.php'),
        ];
    }
}
