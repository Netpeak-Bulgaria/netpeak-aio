<?php

declare(strict_types=1);

namespace Netpeak\Admin;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Static helper for queuing WP admin notices.
 *
 * @since 0.1.0
 */
final class Notices
{
    public const TYPE_INFO    = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR   = 'error';

    /**
     * @param string $message
     * @param string $type One of self::TYPE_* constants.
     *
     * @return void
     */
    public static function add(string $message, string $type = self::TYPE_INFO): void
    {
        add_action('admin_notices', static function () use ($message, $type): void {
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p><strong>Netpeak AIO:</strong> %2$s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
}
