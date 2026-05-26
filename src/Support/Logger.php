<?php

declare(strict_types=1);

namespace Netpeak\Support;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Thin wrapper around error_log, gated by WP_DEBUG.
 *
 * @since 0.1.0
 */
final class Logger
{
    public const LEVEL_INFO    = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR   = 'ERROR';

    /**
     * @param string              $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::write(self::LEVEL_INFO, $message, $context);
    }

    /**
     * @param string              $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * @param string              $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::write(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * @param string              $level
     * @param string              $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    private static function write(string $level, string $message, array $context): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $line = sprintf(
            '[Netpeak AIO][%s] %s%s',
            $level,
            $message,
            !empty($context) ? ' ' . wp_json_encode($context) : ''
        );

        error_log($line);
    }
}
