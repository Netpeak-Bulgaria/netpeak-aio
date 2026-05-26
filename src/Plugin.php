<?php

declare(strict_types=1);

namespace Netpeak;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Admin\AdminMenu;
use Netpeak\Api\OAuth\GoogleOAuthClient;
use Netpeak\Api\OAuth\TokenRefresher;
use Netpeak\Api\OAuth\TokenStorage as GoogleTokenStorage;
use Netpeak\Api\SearchConsole\SearchConsoleClient;
use Netpeak\Api\Analytics\GoogleAnalyticsClient;
use Netpeak\Frontend\BodyInjector;
use Netpeak\Frontend\HeadInjector;
use Netpeak\Integrations\GoogleAnalytics;
use Netpeak\Integrations\MetaPixel;
use Netpeak\Integrations\SearchConsole;
use Netpeak\Integrations\TagManager;
use Netpeak\Rest\RestRouter;
use Netpeak\Settings\SettingsRepository;
use Netpeak\Support\Assets;
use Netpeak\Api\Meta\TokenStorage as MetaTokenStorage;

/**
 * Plugin orchestrator: owns the DI container and wires hooks.
 *
 * @since 0.1.0
 * @package Netpeak
 */
final class Plugin
{
    private const VERSION_OPTION = 'netpeak_aio_version';

    /**
     * @var self|null
     */
    private static ?self $instance = null;

    private readonly Container $container;

    /**
     * @param string $plugin_file Absolute path to the main plugin file.
     */
    private function __construct(private readonly string $plugin_file)
    {
        $this->container = new Container();
        $this->register_services();
        $this->register_hooks();
    }

    /**
     * Bootstraps the plugin. Idempotent.
     *
     * @param string $plugin_file
     *
     * @return self
     */
    public static function boot(string $plugin_file): self
    {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
        }

        return self::$instance;
    }

    /**
     * @return self|null
     */
    public static function instance(): ?self
    {
        return self::$instance;
    }

    /**
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * @return string
     */
    public function plugin_file(): string
    {
        return $this->plugin_file;
    }

    /**
     * Fires once on plugin activation.
     *
     * @return void
     */
    public static function on_activate(): void
    {
        update_option(self::VERSION_OPTION, NTP_AIO_VERSION, false);
    }

    /**
     * Fires once on plugin deactivation.
     *
     * @return void
     */
    public static function on_deactivate(): void
    {
        delete_transient('netpeak_aio');
    }

    /**
     * @return void
     */
    private function register_services(): void
    {
        $plugin_file = $this->plugin_file;

        $this->container->set(
            SettingsRepository::class,
            static fn (): SettingsRepository => new SettingsRepository()
        );

        $this->container->set(
            GoogleTokenStorage::class,
            static fn (): GoogleTokenStorage => new GoogleTokenStorage()
        );

        $this->container->set(
            MetaTokenStorage::class,
            static fn (): MetaTokenStorage => new MetaTokenStorage()
        );

        $this->container->set(
            GoogleOAuthClient::class,
            static function (Container $c): GoogleOAuthClient {
                /** @var SettingsRepository $settings */
                $settings = $c->get(SettingsRepository::class);

                return new GoogleOAuthClient(
                    (string) $settings->get('oauth.client_id', ''),
                    (string) $settings->get('oauth.client_secret', ''),
                );
            }
        );

        $this->container->set(
            GoogleAnalyticsClient::class,
            static fn (Container $c): GoogleAnalyticsClient => new GoogleAnalyticsClient(
                $c->get(TokenRefresher::class)
            )
        );

        $this->container->set(
            TokenRefresher::class,
            static fn (Container $c): TokenRefresher => new TokenRefresher(
                $c->get(GoogleOAuthClient::class),
                $c->get(GoogleTokenStorage::class),
            )
        );

        $this->container->set(
            SearchConsoleClient::class,
            static fn (Container $c): SearchConsoleClient => new SearchConsoleClient(
                $c->get(TokenRefresher::class)
            )
        );

        $this->container->set(
            GoogleAnalytics::class,
            static fn (Container $c): GoogleAnalytics => new GoogleAnalytics(
                $c->get(SettingsRepository::class)
            )
        );

        $this->container->set(
            TagManager::class,
            static fn (Container $c): TagManager => new TagManager(
                $c->get(SettingsRepository::class)
            )
        );

        $this->container->set(
            SearchConsole::class,
            static fn (Container $c): SearchConsole => new SearchConsole(
                $c->get(SettingsRepository::class)
            )
        );

        $this->container->set(
            MetaPixel::class,
            static fn (Container $c): MetaPixel => new MetaPixel(
                $c->get(SettingsRepository::class)
            )
        );

        $this->container->set(
            AdminMenu::class,
            static fn (Container $c): AdminMenu => new AdminMenu($c)
        );

        $this->container->set(
            RestRouter::class,
            static fn (Container $c): RestRouter => new RestRouter($c)
        );

        $this->container->set(
            Assets::class,
            static fn (): Assets => new Assets($plugin_file)
        );
    }

    /**
     * @return void
     */
    private function register_hooks(): void
    {
        $injectors = [
            $this->container->get(GoogleAnalytics::class),
            $this->container->get(TagManager::class),
            $this->container->get(SearchConsole::class),
            $this->container->get(MetaPixel::class),
        ];

        foreach ($injectors as $integration) {
            $integration->register();
        }

        (new HeadInjector($injectors))->register();
        (new BodyInjector($injectors))->register();

        /** @var RestRouter $rest */
        $rest = $this->container->get(RestRouter::class);
        $rest->register();

        if (is_admin()) {
            /** @var AdminMenu $admin */
            $admin = $this->container->get(AdminMenu::class);
            $admin->register();

            /** @var Assets $assets */
            $assets = $this->container->get(Assets::class);
            $assets->register();
        }
    }
}
