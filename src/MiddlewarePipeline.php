<?php

declare(strict_types=1);

namespace Solo\Application;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var array<string|MiddlewareInterface> */
    private array $middlewares = [];
    private int $index = 0;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly RequestHandlerInterface $finalHandler
    ) {
    }

    public function add(string|MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function addFromArray(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            $this->add($middleware);
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middlewares[$this->index])) {
            return $this->finalHandler->handle($request);
        }

        $middleware = $this->resolve($this->middlewares[$this->index]);
        $this->index++;

        return $middleware->process($request, $this);
    }

    private function resolve(string|MiddlewareInterface $middleware): MiddlewareInterface
    {
        if (is_string($middleware)) {
            $middleware = $this->container->get($middleware);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new InvalidArgumentException('Middleware must implement MiddlewareInterface');
        }

        return $middleware;
    }
}
