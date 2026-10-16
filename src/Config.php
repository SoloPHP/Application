<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Http\Server\MiddlewareInterface;

final readonly class Config
{
    /**
     * @param string $basePath Application root; not used by the kernel, available to your services via Config
     * @param string $routesPath PHP file that returns a callable receiving the router
     * @param array<class-string> $providers Classes with a register(WritableContainerInterface) method
     * @param array<MiddlewareInterface|callable|string> $middleware Global middleware, outermost first
     */
    public function __construct(
        public string $basePath,
        public string $routesPath,
        public array $providers,
        public array $middleware,
    ) {
    }
}
