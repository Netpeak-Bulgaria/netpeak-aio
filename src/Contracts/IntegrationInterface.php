<?php

declare(strict_types=1);


namespace Netpeak\Contracts;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base contract for every pluggable integration (GA4, GTM, GSC, ...).
 *
 * @since 0.1.0
 */
interface IntegrationInterface
{
    /**
     * Unique machine key. Used as a prefix in settings and as an identifier in logs.
     *
     * @return string e.g. 'ga4', 'gtm', 'gsc'
     */
    public function key(): string;

    /**
     * Whether the integration has all required settings to operate.
     *
     * @return bool
     */
    public function is_configured(): bool;

    /**
     * Called once during plugin boot. Integrations register their hooks here.
     *
     * @return void
     */
    public function register(): void;
}
