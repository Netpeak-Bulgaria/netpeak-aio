<?php
/**
 * Netpeak Analytics Kit - Uninstall routine.
 *
 * Runs when the user clicks "Delete" on the plugin in WP admin (NOT on deactivate).
 * Removes plugin data only when "Delete all plugin data" is on (Settings -> Data, or the
 * checkbox in the deactivation dialog). Otherwise everything is kept, so a reinstall
 * finds the configuration and the Google connection as they were.
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
 * The flag lives in the main settings option (same storage as SettingsRepository: site option on multisite).
 */
$netpeak_aio_settings = is_multisite()
    ? get_site_option('netpeak_aio_settings', [])
    : get_option('netpeak_aio_settings', []);

$netpeak_aio_delete = is_array($netpeak_aio_settings)
    && !empty($netpeak_aio_settings['general']['delete_data_on_uninstall']);

if (!$netpeak_aio_delete) {
    return;
}

/**
 * Single-site cleanup. Called once on classic installs, and once per blog
 * on multisite network uninstalls.
 */
$netpeak_aio_cleanup = static function (): void {
    global $wpdb;

    $options = [
        'netpeak_aio_settings',
        'netpeak_aio_google_oauth_tokens',
        'netpeak_aio_version',
        'netpeak_aio_meta_capi_token',
        'netpeak_aio_cache_ver',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    delete_transient('netpeak_aio_google_oauth_state');

    // Cached Google API responses (Netpeak\Support\Cache).
    $transient_like = $wpdb->esc_like('_transient_netpeak_aio_c_') . '%';
    $timeout_like   = $wpdb->esc_like('_transient_timeout_netpeak_aio_c_') . '%';
    $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_like, $timeout_like)
    );

    delete_metadata('user', 0, 'netpeak_aio_dismissed_notices', '', true);

    // Order flags (purchase tracked, Meta event id): classic order meta and the HPOS table.
    $meta_like = $wpdb->esc_like('_ntp_aio_') . '%';
    $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $meta_like)
    );

    $hpos_table = $wpdb->prefix . 'wc_orders_meta';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($hpos_table))) === $hpos_table) {
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare("DELETE FROM {$hpos_table} WHERE meta_key LIKE %s", $meta_like)
        );
    }
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
