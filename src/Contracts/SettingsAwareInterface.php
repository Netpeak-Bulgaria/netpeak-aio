<?php

declare(strict_types=1);

namespace Netpeak\Contracts;
if (!defined('ABSPATH')) {
    exit;
}


use Netpeak\Settings\SettingsRepository;

/**
 * Marker + accessor for services that depend on the settings store.
 *
 * @since 0.1.0
 */
interface SettingsAwareInterface
{
    /**
     * @return SettingsRepository
     */
    public function settings(): SettingsRepository;
}
