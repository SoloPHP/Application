<?php

declare(strict_types=1);

namespace Solo\Application;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;

/**
 * Middleware is a MiddlewareInterface, a callable with the same signature as process(),
 * or a container id that resolves to either; ids are resolved when the request reaches them.
 * Strings follow the same rule as route handlers, see CallableResolver.
 */
final class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface|callable|string> */
    private array $middlewares = [];
    private int $index = 0;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly RequestHandlerInterface $finalHandler
    ) {
    }

    public function add(MiddlewareInterface|callable|string $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * @param array<MiddlewareInterface|callable|string> $middlewares
     */
    public function addFromArray(array $middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->add($middleware);
        }
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middlewares[$this->index])) {
            return $this->finalHandler->handle($request);
        }

        $entry = $this->middlewares[$this->index];
        $middleware = CallableResolver::resolve($this->container, $entry);

        // Each step hands on its own copy, so the pipeline can be reused and middleware may call it more than once
        $next = clone $this;
        $next->index++;

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $next);
        }

        if (!is_callable($middleware)) {
            throw new InvalidArgumentException(sprintf(
                'Middleware %s must resolve to %s or a callable, %s given',
                CallableResolver::describe($entry),
                MiddlewareInterface::class,
                get_debug_type($middleware)
            ));
        }

        $response = $middleware($request, $next);

        if (!$response instanceof ResponseInterface) {
            throw new UnexpectedValueException(sprintf(
                'Middleware %s must return %s, %s returned',
                CallableResolver::describe($entry),
                ResponseInterface::class,
                get_debug_type($response)
            ));
        }

        return $response;
    }
}
