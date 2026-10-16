<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use Solo\Container\Container;
use Solo\Container\Exceptions\NotFoundException;
use Solo\Contracts\Container\WritableContainerInterface;

/**
 * Registers into an inner container, whose factories therefore receive the inner one,
 * and falls back to fixed services only this outer container knows.
 */
final class DelegatingContainer implements WritableContainerInterface
{
    private readonly Container $inner;

    /**
     * @param array<string, mixed> $fallback
     */
    public function __construct(private readonly array $fallback)
    {
        $this->inner = new Container();
    }

    public function set(string $id, callable $factory): void
    {
        $this->inner->set($id, $factory);
    }

    public function get(string $id): mixed
    {
        if ($this->inner->has($id)) {
            return $this->inner->get($id);
        }

        return $this->fallback[$id] ?? throw new NotFoundException("Service '$id' not found");
    }

    public function has(string $id): bool
    {
        return $this->inner->has($id) || isset($this->fallback[$id]);
    }
}
