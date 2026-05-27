<?php
/**
 * Netpeak Analytics Kit - Uninstall routine.
 *
 * Runs when the user clicks "Delete" on the plugin in WP admin
 * (NOT on deactivate). Removes every trace of the plugin from the
 * database: options, transients, scheduled hooks, user meta.
 *
 * Multisite-aware: iterates blogs when network-active.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

// Safety: only run during uninstall, never on direct access.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Single-site cleanup. Called once on classic installs, and once per blog
 * on multisite network uninstalls.
 */
$netpeak_aio_cleanup = static function (): void {
    $options = [
        'netpeak_aio_settings',
        'google_oauth_tokens',
        'google_aio_oauth_tokens',
        'netpeak_aio_version',
        'NTP_AIO_VERSION',
        'ntp_aio_meta_capi_token',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    delete_transient('google_aio_oauth_state');

    global $wpdb;

    $transient_prefixes = [
        '_transient_netpeak_aio_',
        '_transient_timeout_netpeak_aio_',
        '_transient_google_aio_',
        '_transient_timeout_google_aio_',
        '_transient_ntp_aio_meta_pixel_deferred_',
        '_transient_timeout_ntp_aio_meta_pixel_deferred_',
    ];

    foreach ($transient_prefixes as $prefix) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Wildcard DELETE on uninstall, no caching needed.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );
    }

    $scheduled_hooks = [
        'netpeak_aio_token_refresh',
        'netpeak_aio_report_refresh',
    ];

    foreach ($scheduled_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    delete_metadata('user', 0, 'netpeak_aio_dismissed_notices', '', true);
    delete_metadata('post', 0, '_ntp_aio_meta_purchase_event_id', '', true);
};

if (is_multisite()) {
    $sites = get_sites(['number' => 500, 'fields' => 'ids']);

    foreach ($sites as $blog_id) {
        switch_to_blog((int) $blog_id);
        $netpeak_aio_cleanup();
        restore_current_blog();
    }

    delete_site_option('netpeak_aio_settings');
} else {
    $netpeak_aio_cleanup();
}
