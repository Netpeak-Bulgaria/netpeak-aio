<?php

declare(strict_types=1);

namespace Netpeak\Frontend;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Contracts\ScriptInjectorInterface;

/**
 * Aggregates output right after <body> from every ScriptInjectorInterface instance.
 *
 * Requires the active theme to call wp_body_open() in header.php.
 *
 * @since 0.1.0
 */
final class BodyInjector
{
    private const HOOK     = 'wp_body_open';
    private const PRIORITY = 1;

    /**
     * @param ScriptInjectorInterface[] $injectors
     */
    public function __construct(private readonly array $injectors)
    {
    }

    /**
     * @return void
     */
    public function register(): void
    {
        add_action(self::HOOK, [$this, 'output'], self::PRIORITY);
    }

    /**
     * @return void
     */
    public function output(): void
    {
        foreach ($this->injectors as $injector) {
            // Each integration is responsible for escaping its own output.
            // The HTML here contains intentional <script>/<iframe> tags from
            // Meta Pixel, GTM and similar — escaping would break functionality.
            $html = (string) apply_filters('google_aio_body_html', $injector->render_body(), $injector);
            if ($html !== '') {
                echo $html . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
    }
}
