<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use LogicException;
use Solo\Container\Container;
use Solo\Contracts\Container\WritableContainerInterface;

/**
 * Refuses to replace a service once it has been resolved, like Pimple or Laminas ServiceManager.
 */
final class FreezingContainer implements WritableContainerInterface
{
    private readonly Container $inner;

    /** @var array<string, true> */
    private array $resolved = [];

    public function __construct()
    {
        $this->inner = new Container();
    }

    public function set(string $id, callable $factory): void
    {
        if (isset($this->resolved[$id])) {
            throw new LogicException("Cannot override frozen service \"$id\"");
        }

        $this->inner->set($id, fn() => $factory($this));
    }

    public function get(string $id): mixed
    {
        $this->resolved[$id] = true;
        return $this->inner->get($id);
    }

    public function has(string $id): bool
    {
        return $this->inner->has($id);
    }
}
