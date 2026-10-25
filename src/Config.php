<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Http\Server\MiddlewareInterface;

final readonly class Config
{
    /**
     * @param string $basePath Application root; not used by the kernel, available to your services via Config
     * @param string $routesPath PHP file that returns a callable receiving the route collector
     * @param array<class-string> $providers Classes with a register(WritableContainerInterface) method
     * @param array<MiddlewareInterface|callable|string> $middleware Global middleware, outermost first
     * @param string|null $routesCachePath Absolute path of a PHP file that keeps the built router, or null to
     *                                     build it on every boot; delete the file whenever routes change
     */
    public function __construct(
        public string $basePath,
        public string $routesPath,
        public array $providers,
        public array $middleware,
        public ?string $routesCachePath = null,
    ) {
    }
}
