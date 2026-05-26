<?php

declare(strict_types=1);

namespace Netpeak\Rest\Controllers;
if (!defined('ABSPATH')) {
    exit;
}
use Netpeak\Container;
use Netpeak\Rest\RestRouter;
use WP_REST_Request;

/**
 * Base class for every REST controller.
 *
 * @since 0.1.0
 */
abstract class AbstractController
{
    /**
     * @param Container $container
     */
    public function __construct(protected readonly Container $container)
    {
    }

    /**
     * Registers routes with the WP REST API.
     *
     * @return void
     */
    abstract public function register(): void;

    /**
     * @return string
     */
    protected function namespace(): string
    {
        return RestRouter::NAMESPACE;
    }

    /**
     * Permission callback for admin-only endpoints.
     *
     * @param WP_REST_Request $request
     *
     * @return bool
     */
    public function check_admin_permissions(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }
}
