<?php

declare(strict_types=1);

namespace Netpeak\Integrations;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Contracts\IntegrationInterface;
use Netpeak\Contracts\ScriptInjectorInterface;
use Netpeak\Contracts\SettingsAwareInterface;
use Netpeak\Settings\SettingsRepository;

/**
 * Base class for every integration. Provides default no-op implementations
 * of register() and the ScriptInjectorInterface methods.
 *
 * @since 0.1.0
 */
abstract class AbstractIntegration implements IntegrationInterface, ScriptInjectorInterface, SettingsAwareInterface
{
    /**
     * @param SettingsRepository $settings
     */
    public function __construct(protected readonly SettingsRepository $settings)
    {
    }

    /**
     * @return SettingsRepository
     */
    public function settings(): SettingsRepository
    {
        return $this->settings;
    }

    /**
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * @return string
     */
    public function render_head(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function render_body(): string
    {
        return '';
    }

    /**
     * @return bool
     */
    protected function is_enabled(): bool
    {
        return (bool) $this->settings->get($this->key() . '.enabled', false);
    }

    /**
     * @return bool
     */
    protected function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
    }
}
