<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Solo\Contracts\Container\WritableContainerInterface;

/**
 * A valid container that calls the factory on every get() instead of sharing one instance.
 */
final class FactoryContainer implements WritableContainerInterface
{
    /** @var array<string, callable> */
    private array $factories = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (isset($this->factories[$id])) {
            return ($this->factories[$id])($this);
        }

        if (class_exists($id)) {
            return new $id();
        }

        throw new class ("Service '$id' not found") extends RuntimeException implements NotFoundExceptionInterface {
        };
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || class_exists($id);
    }
}
