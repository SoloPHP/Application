<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use Solo\Contracts\Router\RouterInterface;

/**
 * A decorator without AllowedMethodsInterface that forwards every other call to the router it wraps,
 * so getAllowedMethods() is reachable only through __call(), as on solophp/router 3.3 and 3.4.
 */
final class ForwardingRouter implements RouterInterface
{
    public function __construct(private readonly RouterInterface $inner)
    {
    }

    /**
     * @param callable|array{class-string, string}|string $handler
     * @param array{
     *     group?: string,
     *     middlewares?: array<int, callable|string>,
     *     name?: string|null,
     *     attributes?: array<string, mixed>
     * } $options
     */
    public function addRoute(string $method, string $path, callable|array|string $handler, array $options = []): object
    {
        return $this->inner->addRoute($method, $path, $handler, $options);
    }

    /**
     * @return array{
     *     handler: callable|array{class-string, string}|string,
     *     params: array<string, string>,
     *     middlewares: array<int, callable|string>,
     *     name?: string|null,
     *     attributes?: array<string, mixed>
     * }|false
     */
    public function match(string $method, string $uri): array|false
    {
        return $this->inner->match($method, $uri);
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->inner->$name(...$arguments);
    }
}
