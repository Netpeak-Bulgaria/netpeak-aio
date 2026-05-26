<?php

declare(strict_types=1);

namespace Netpeak\Rest;
if (!defined('ABSPATH')) {
    exit;
}
use Netpeak\Container;
use Netpeak\Rest\Controllers\OAuthController;
use Netpeak\Rest\Controllers\SearchConsoleController;
use Netpeak\Rest\Controllers\SettingsController;
use Netpeak\Rest\Controllers\AnalyticsController;
use Netpeak\Rest\Controllers\GtmProductController;

/**
 * Bootstraps all REST controllers under the plugin namespace.
 *
 * @since 0.1.0
 */
final class RestRouter
{
    public const NAMESPACE = 'netpeak-aio/v1';

    /**
     * @param Container $container
     */
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return void
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * @return void
     */
    public function register_routes(): void
    {
        (new SettingsController($this->container))->register();
        (new SearchConsoleController($this->container))->register();
        (new OAuthController($this->container))->register();
        (new AnalyticsController($this->container))->register();
        //(new GtmProductController($this->container))->register();
    }
}
