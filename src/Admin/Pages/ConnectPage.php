<?php

declare(strict_types=1);


namespace Netpeak\Admin\Pages;

use Netpeak\Api\OAuth\TokenStorage;

if (!defined('ABSPATH')) {
    exit;
}

final class ConnectPage extends AbstractPage
{
    protected function title(): string
    {
        return 'Netpeak AIO — Connect Google';
    }

    protected function view(): string
    {
        return 'connect';
    }

    /**
     * @return array{connected: bool}
     */
    protected function data(): array
    {
        /** @var TokenStorage $storage */
        $storage = $this->container->get(TokenStorage::class);

        return ['connected' => $storage->has_refresh_token()];
    }
}
