<?php

declare(strict_types=1);


namespace Netpeak\Rest\Controllers;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Settings\SettingsRepository;
use Netpeak\Settings\SettingsSchema;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for reading and writing plugin settings.
 *
 * Secrets (see SettingsRepository::ENCRYPTED_PATHS) are never returned to the
 * browser. On save, empty secret fields are interpreted as "keep existing"
 * so the UI doesn't need to round-trip the stored value.
 *
 * @since 0.1.0
 */
final class SettingsController extends AbstractController
{
    /**
     * @return void
     */
    public function register(): void
    {
        register_rest_route($this->namespace(), '/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_permissions'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permissions'],
            ],
        ]);
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_settings(WP_REST_Request $request): WP_REST_Response
    {
        /** @var SettingsRepository $repo */
        $repo = $this->container->get(SettingsRepository::class);

        return new WP_REST_Response($repo->public_data(), 200);
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function update_settings(WP_REST_Request $request): WP_REST_Response
    {
        $input = (array) $request->get_json_params();

        /** @var SettingsRepository $repo */
        $repo = $this->container->get(SettingsRepository::class);

        // Preserve existing encrypted secret if the UI sent empty
        $incoming_secret = (string) ($input['oauth']['client_secret'] ?? '');
        if ($incoming_secret === '') {
            $existing_ciphertext = $repo->raw('oauth.client_secret');
            if ($existing_ciphertext !== '') {

                $input['oauth']['client_secret'] = (string) $repo->get('oauth.client_secret', '');
            }
        }

        $clean = SettingsSchema::sanitize($input);
        $repo->save($clean);

        return new WP_REST_Response([
            'saved'    => true,
            'settings' => $repo->public_data(),
        ], 200);
    }
}
