<?php

declare(strict_types=1);

namespace Netpeak\Support;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transient cache for Google API responses shown on the dashboard.
 *
 * Only successful results are stored: when the producer throws, nothing is cached.
 * flush() bumps a version that is part of every key, so old entries are never read again
 * and expire on their own (uninstall.php removes the leftovers).
 *
 * Filter: netpeak_aio_cache_ttl (int $ttl, string $key). Return 0 to disable caching.
 *
 * @since 0.1.2
 */
final class Cache
{
    public const DEFAULT_TTL = 1800;

    private const PREFIX         = 'netpeak_aio_c_';
    private const VERSION_OPTION = 'netpeak_aio_cache_ver';

    /**
     * @param string   $key     Human-readable key, the request parameters must be part of it.
     * @param callable $producer
     * @param int      $ttl     Seconds.
     * @param bool     $refresh Skip the read and overwrite the stored value.
     *
     * @return mixed
     */
    public static function remember(string $key, callable $producer, int $ttl = self::DEFAULT_TTL, bool $refresh = false): mixed
    {
        $ttl  = (int) apply_filters('netpeak_aio_cache_ttl', $ttl, $key);
        $name = self::PREFIX . md5(self::version() . '|' . $key);

        if (!$refresh && $ttl > 0) {
            $hit = get_transient($name);
            if ($hit !== false) {
                return $hit;
            }
        }

        $value = $producer();

        if ($ttl > 0) {
            set_transient($name, $value, $ttl);
        }

        return $value;
    }

    /**
     * @return void
     */
    public static function flush(): void
    {
        update_option(self::VERSION_OPTION, (string) microtime(true), false);
    }

    /**
     * @return string
     */
    private static function version(): string
    {
        return (string) get_option(self::VERSION_OPTION, '0');
    }
}
