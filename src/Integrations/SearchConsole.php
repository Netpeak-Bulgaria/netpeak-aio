<?php

declare(strict_types=1);


namespace Netpeak\Integrations;
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Google Search Console: HTML file verification + API access.
 *
 * @since 0.1.0
 */
final class SearchConsole extends AbstractIntegration
{
    /**
     * @return string
     */
    public function key(): string
    {
        return 'gsc';
    }

    /**
     * @return bool
     */
    public function is_configured(): bool
    {
        $file = (string) $this->settings->get('gsc.verification_file', '');
        $url  = (string) $this->settings->get('gsc.site_url', '');

        return $file !== '' || $url !== '';
    }

    /**
     * @return void
     */
    public function register(): void
    {
        add_action('init', [$this, 'maybe_serve_verification_file'], 0);
    }

    /**
     * Intercepts requests to the Google verification file and serves the expected body.
     *
     * @return void
     */
    public function maybe_serve_verification_file(): void
    {
        $filename = trim((string) $this->settings->get('gsc.verification_file', ''));
        if ($filename === '') {
            return;
        }

        if (preg_match('/^google[a-f0-9]+\.html$/', $filename) !== 1) {
            return;
        }

        $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path        = (string) parse_url($request_uri, PHP_URL_PATH);
        $slug        = basename($path);

        if ($slug !== $filename) {
            return;
        }

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        echo "google-site-verification: {$filename}";
        exit;
    }
}
