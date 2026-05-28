<?php

declare(strict_types=1);

namespace Netpeak\Contracts;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contract for integrations that output HTML into <head> or right after <body>.
 *
 * @since 0.1.0
 */
interface ScriptInjectorInterface
{
    /**
     * HTML to echo inside wp_body_open (e.g. GTM noscript iframe).
     *
     * @return string
     */
    public function render_body(): string;
}
