<?php

declare(strict_types=1);


namespace Netpeak;
if (!defined('ABSPATH')) {
    exit;
}
use Closure;
use RuntimeException;

/**
 * Minimal DI container with lazy factories.
 *
 * @since 0.1.0
 */
final class Container
{
    /**
     * @var array<string, Closure>
     */
    private array $factories = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @param string  $id
     * @param Closure $factory Receives the container instance as the only argument.
     *
     * @return void
     */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * @param string $id
     *
     * @throws RuntimeException When the requested service is not registered.
     *
     * @return object
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException("Service not registered: {$id}");
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }
}
