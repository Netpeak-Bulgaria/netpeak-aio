<?php

declare(strict_types=1);

namespace Netpeak\Frontend;
if (!defined('ABSPATH')) {
    exit;
}
use Netpeak\Contracts\ScriptInjectorInterface;

/**
 * Aggregates <head> output from every ScriptInjectorInterface instance.
 *
 * @since 0.1.0
 */
final class HeadInjector
{
    private const HOOK     = 'wp_head';
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
            $html = (string) apply_filters('google_aio_head_html', $injector->render_head(), $injector);
            if ($html !== '') {
                echo $html . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
    }
}
