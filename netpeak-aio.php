<?php
/**
 * Plugin Name:       Analytics Kit All-in-One by Netpeak Bulgaria
 * Plugin URI:        https://netpeak.bg/services/wordpress-website-development/
 * Description:       All your marketing pixels and analytics in one place. Connect Google Analytics 4, Tag Manager, Search Console, Meta Pixel, without touching your theme.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Netpeak Bulgaria
 * Author URI:        https://netpeak.bg/services/wordpress-website-development/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       netpeak-aio
 * Domain Path:       /languages
 */

declare(strict_types=1);


if (!defined('ABSPATH')) {
    exit;
}

if (defined('NTP_AIO_VERSION')) {
    return;
}

/**
 *
 * ███╗   ██╗███████╗████████╗██████╗ ███████╗ █████╗ ██╗  ██╗
 * ████╗  ██║██╔════╝╚══██╔══╝██╔══██╗██╔════╝██╔══██╗██║ ██╔╝
 * ██╔██╗ ██║█████╗     ██║   ██████╔╝█████╗  ███████║█████╔╝
 * ██║╚██╗██║██╔══╝     ██║   ██╔═══╝ ██╔══╝  ██╔══██║██╔═██╗
 * ██║ ╚████║███████╗   ██║   ██║     ███████╗██║  ██║██║  ██╗
 * ╚═╝  ╚═══╝╚══════╝   ╚═╝   ╚═╝     ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝
 * @since 0.1.0
 * @author Netpeak Bulgaria <info@netpeak.bg>
 * @package Netpeak
 */


define('NTP_AIO_VERSION', '0.1.0');
define('NTP_AIO_FILE', __FILE__);
define('NTP_AIO_DIR', plugin_dir_path(__FILE__));
define('NTP_AIO_URL', plugin_dir_url(__FILE__));
define('NTP_AIO_BASENAME', plugin_basename(__FILE__));
define('NTP_AIO_MIN_PHP', '8.2');
define('NTP_AIO_MIN_WP', '6.7');


/**
 * @return true|string
 */
$google_aio_requirements_check = static function (): bool|string {
    if (version_compare(PHP_VERSION, NTP_AIO_MIN_PHP, '<')) {
        return sprintf(
            'Requires PHP %s or above. Current version: %s.',
            NTP_AIO_MIN_PHP,
            PHP_VERSION
        );
    }

    global $wp_version;
    if (version_compare($wp_version, NTP_AIO_MIN_WP, '<')) {
        return sprintf(
            'Requires WordPress %s or above. Current version: %s.',
            NTP_AIO_MIN_WP,
            $wp_version
        );
    }

    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        return 'Not found <code>vendor/autoload.php</code>. Please run <code>composer install</code> in the plugin directory.';
    }

    return true;
};

$google_aio_check = $google_aio_requirements_check();
if ($google_aio_check !== true) {
    add_action('admin_notices', static function () use ($google_aio_check): void {
        printf(
            '<div class="notice notice-error"><p><strong>Netpeak AIO:</strong> %s</p></div>',
            wp_kses($google_aio_check, ['code' => []])
        );
    });
    return;
}


require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, static function () use ($google_aio_requirements_check): void {
    $check = $google_aio_requirements_check();
    if ($check !== true) {
        deactivate_plugins(NTP_AIO_BASENAME);
        wp_die(
            wp_kses(
                '<strong>Netpeak AIO</strong> не может быть активирован. ' . $check,
                ['strong' => [], 'code' => []]
            ),
            'Plugin activation error',
            ['back_link' => true]
        );
    }

    \Netpeak\Plugin::on_activate();
});


register_deactivation_hook(__FILE__, [\Netpeak\Plugin::class, 'on_deactivate']);


add_action('plugins_loaded', static function (): void {
    \Netpeak\Plugin::boot(NTP_AIO_FILE);
}, 10);
