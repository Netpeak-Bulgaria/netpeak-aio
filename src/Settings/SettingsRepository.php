<?php

declare(strict_types=1);

namespace Netpeak\Settings;
if (!defined('ABSPATH')) {
    exit;
}
use Netpeak\Support\Encryption;


/**
 * Read/write access to the plugin settings option. Supports dot-notation.
 *
 * Paths listed in ENCRYPTED_PATHS are transparently decrypted on read.
 * Encryption on write is handled by SettingsSchema.
 *
 * @since 0.1.0
 */
final class SettingsRepository
{
    private const OPTION_KEY = 'netpeak_aio_settings';

    /**
     * Dot-paths whose stored value is AES-256-GCM ciphertext.
     * Also the set of paths stripped from REST responses by public_data().
     *
     * @var list<string>
     */
    private const ENCRYPTED_PATHS = [
        'oauth.client_secret',
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stored = is_multisite()
            ? get_site_option(self::OPTION_KEY, [])
            : get_option(self::OPTION_KEY, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        return $this->cache = array_replace_recursive(Defaults::all(), $stored);
    }

    /**
     * Returns settings tree with ENCRYPTED_PATHS blanked — safe to serialize into REST responses.
     *
     * @return array<string, mixed>
     */
    public function public_data(): array
    {
        $data = $this->all();

        foreach (self::ENCRYPTED_PATHS as $path) {
            $this->put($data, $path, '');
        }

        return $data;
    }

    /**
     * @param string $path
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $value    = $this->all();

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        if (in_array($path, self::ENCRYPTED_PATHS, true) && is_string($value) && $value !== '') {
            return Encryption::decrypt($value);
        }

        return $value;
    }

    /**
     * Raw value as stored (no decryption). Used by the controller to detect
     * whether an encrypted field is set without exposing plaintext.
     *
     * @param string $path
     *
     * @return string
     */
    public function raw(string $path): string
    {
        $segments = explode('.', $path);
        $value    = $this->all();

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return '';
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $data Already sanitized.
     *
     * @return bool
     */
    public function save(array $data): bool
    {
        $this->cache = array_replace_recursive(Defaults::all(), $data);

        return is_multisite()
            ? update_site_option(self::OPTION_KEY, $this->cache)
            : update_option(self::OPTION_KEY, $this->cache, false);
    }

    /**
     * @return void
     */
    public function flush_cache(): void
    {
        $this->cache = null;
    }

    /**
     * @param array<string, mixed> $data
     * @param string               $path
     * @param mixed                $value
     *
     * @return void
     */
    private function put(array &$data, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $last     = array_pop($segments);
        $node     = &$data;

        foreach ($segments as $segment) {
            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                $node[$segment] = [];
            }
            $node = &$node[$segment];
        }

        $node[$last] = $value;
    }
}
