<?php

declare(strict_types=1);

namespace Netpeak\Rest\Controllers;
if (!defined('ABSPATH')) {
    exit;
}


use Netpeak\Admin\AdminMenu;
use Netpeak\Api\OAuth\GoogleOAuthClient;
use Netpeak\Api\OAuth\TokenStorage;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * OAuth 2.0 flow endpoints: start, callback, disconnect.
 *
 * @since 0.1.0
 */
final class OAuthController extends AbstractController
{
    private const STATE_TRANSIENT = 'netpeak_aio_google_oauth_state';
    private const STATE_TTL       = 15 * MINUTE_IN_SECONDS;

    /**
     * @return void
     */
    public function register(): void
    {
        register_rest_route($this->namespace(), '/oauth/start', [
            'methods'             => 'GET',
            'callback'            => [$this, 'start'],
            'permission_callback' => [$this, 'check_admin_permissions'],
        ]);

        register_rest_route($this->namespace(), '/oauth/callback', [
            'methods'             => 'GET',
            'callback'            => [$this, 'callback'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace(), '/oauth/disconnect', [
            'methods'             => 'POST',
            'callback'            => [$this, 'disconnect'],
            'permission_callback' => [$this, 'check_admin_permissions'],
        ]);
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function start(WP_REST_Request $request): WP_REST_Response
    {
        /** @var GoogleOAuthClient $client */
        $client = $this->container->get(GoogleOAuthClient::class);

        $state = wp_generate_password(32, false);
        set_transient(self::STATE_TRANSIENT, $state, self::STATE_TTL);

        $url = $client->build_authorization_url($state, $this->redirect_uri());

        return new WP_REST_Response(['url' => $url], 200);
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function callback(WP_REST_Request $request): WP_REST_Response
    {
        $code  = (string) $request->get_param('code');
        $state = (string) $request->get_param('state');

        $expected = (string) get_transient(self::STATE_TRANSIENT);
        delete_transient(self::STATE_TRANSIENT);

        if ($code === '' || $state === '' || $expected === '' || !hash_equals($expected, $state)) {
            return new WP_REST_Response(['error' => 'Invalid OAuth state.'], 400);
        }

        try {
            /** @var GoogleOAuthClient $client */
            $client = $this->container->get(GoogleOAuthClient::class);
            $tokens = $client->exchange_code($code, $this->redirect_uri());

            /** @var TokenStorage $storage */
            $storage = $this->container->get(TokenStorage::class);
            $storage->save($tokens);

            wp_safe_redirect(admin_url('admin.php?page=' . AdminMenu::CONNECT_SLUG . '&connected=1'));
            exit;
        } catch (Throwable $e) {
            return new WP_REST_Response([
                'error' => esc_html__('OAuth token exchange failed. Please reconnect.', 'netpeak-analytics-kit'),
            ], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function disconnect(WP_REST_Request $request): WP_REST_Response
    {
        /** @var TokenStorage $storage */
        $storage = $this->container->get(TokenStorage::class);
        $storage->clear();

        return new WP_REST_Response(['disconnected' => true], 200);
    }

    /**
     * @return string
     */
    private function redirect_uri(): string
    {
        return rest_url($this->namespace() . '/oauth/callback');
    }
}
