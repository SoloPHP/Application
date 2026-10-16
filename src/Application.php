<?php

declare(strict_types=1);

namespace Solo\Application;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\Contracts\Router\RouterInterface;
use UnexpectedValueException;

final readonly class Application implements RequestHandlerInterface
{
    public WritableContainerInterface $container;

    /** The router holding the routes; RoutingMiddleware gets it directly, not through the container */
    public RouterInterface $router;

    public function __construct(
        public Config $config,
        ?WritableContainerInterface $container = null
    ) {
        $this->container = $container ?? $this->createDefaultContainer();
        $this->registerCoreServices();
        $this->registerProviders();
        $this->router = $this->loadRoutes();
    }

    private function createDefaultContainer(): WritableContainerInterface
    {
        if (!class_exists(\Solo\Container\Container::class)) {
            throw new RuntimeException(
                'No container provided. Install solophp/container or pass your own WritableContainerInterface.'
            );
        }

        return new \Solo\Container\Container();
    }

    private function registerCoreServices(): void
    {
        $this->container->set(Config::class, fn() => $this->config);
        $this->container->set(ContainerInterface::class, fn() => $this->container);
        $this->container->set(WritableContainerInterface::class, fn() => $this->container);

        $this->container->set(RouteDispatcher::class, $this->createRouteDispatcher(...));
        $this->container->set(RoutingMiddleware::class, $this->createRoutingMiddleware(...));
    }

    // The factories use $this->container rather than their argument: a container that delegates may
    // pass its inner one, and handlers and route middleware must resolve where global middleware do

    private function createRouteDispatcher(): RouteDispatcher
    {
        return new RouteDispatcher($this->container, $this->container->get(ResponseFactoryInterface::class));
    }

    private function createRoutingMiddleware(): RoutingMiddleware
    {
        return new RoutingMiddleware(
            $this->router,
            $this->container,
            $this->container->get(ResponseFactoryInterface::class)
        );
    }

    private function registerProviders(): void
    {
        foreach ($this->config->providers as $providerClass) {
            $register = [new $providerClass(), 'register'];

            if (!is_callable($register)) {
                throw new InvalidArgumentException(
                    "Service provider $providerClass must have a public register() method"
                );
            }

            $register($this->container);
        }
    }

    private function loadRoutes(): RouterInterface
    {
        $path = $this->config->routesPath;
        // Found the way require finds it: a relative path is looked up in the include path
        $file = stream_resolve_include_path($path);

        if ($file === false || !is_file($file)) {
            throw new RuntimeException("Routes file not found: $path");
        }

        // A static closure keeps $this, and with it the application's internals, out of the routes file
        $routes = (static fn(): mixed => require $file)();

        if (!is_callable($routes)) {
            throw new UnexpectedValueException(sprintf(
                'Routes file %s must return a callable, %s returned',
                $path,
                get_debug_type($routes)
            ));
        }

        $router = $this->container->get(RouterInterface::class);
        // The container stands in for $this: routes can reach services and Config through it
        $routes($router, $this->container);

        return $router;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return (new MiddlewarePipeline($this->container, $this->container->get(RouteDispatcher::class)))
            ->addFromArray($this->config->middleware)
            ->handle($request);
    }

    public function run(?ServerRequestInterface $request = null): void
    {
        $request ??= $this->container->get(ServerRequestInterface::class);
        $response = $this->handle($request);

        // Resolved after handling, so the request can still replace the emitter, e.g. to stream
        $this->container->get(EmitterInterface::class)->emit($response);
    }
}
