<?php

declare(strict_types=1);


namespace Netpeak\Admin\Pages;

use Netpeak\Admin\AdminMenu;
use Netpeak\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base class for every admin page controller.
 *
 * @since 0.1.0
 */
abstract class AbstractPage
{
    /**
     * @param Container $container
     */
    public function __construct(protected readonly Container $container)
    {
    }

    /**
     * Page title shown as <h1>.
     *
     * @return string
     */
    abstract protected function title(): string;

    /**
     * Relative path under /views/admin/ (without extension).
     *
     * @return string
     */
    abstract protected function view(): string;

    /**
     * Data passed to the view scope.
     *
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        return [];
    }

    /**
     * @return void
     */
    public function render(): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die('Insufficient permissions.');
        }

        $title = $this->title();
        $data  = $this->data();
        $view  = NTP_AIO_DIR . 'views/admin/' . $this->view() . '.php';

        if (!file_exists($view)) {
            return;
        }

        include NTP_AIO_DIR . 'views/admin/layout.php';
    }
}
