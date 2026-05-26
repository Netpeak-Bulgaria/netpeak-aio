<?php

declare(strict_types=1);

namespace Netpeak\Support;
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Custom capability registration. Called once on plugin activation.
 *
 * @since 0.1.0
 */
final class Capabilities
{
    public const MANAGE = 'manage_netpeak_aio';

    /**
     * Adds the custom capability to the administrator role if missing.
     *
     * @return void
     */
    public static function bootstrap(): void
    {
        $role = get_role('administrator');
        if ($role !== null && !$role->has_cap(self::MANAGE)) {
            $role->add_cap(self::MANAGE);
        }
    }

    /**
     * Removes the custom capability from all roles. Intended for uninstall.
     *
     * @return void
     */
    public static function teardown(): void
    {
        $roles = wp_roles()->roles;
        foreach (array_keys($roles) as $slug) {
            $role = get_role($slug);
            if ($role !== null && $role->has_cap(self::MANAGE)) {
                $role->remove_cap(self::MANAGE);
            }
        }
    }
}
