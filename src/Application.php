<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\Contracts\Router\RouterInterface;

final readonly class Application
{
    public WritableContainerInterface $container;

    public function __construct(
        public Config $config,
        ?WritableContainerInterface $container = null
    ) {
        $this->container = $container ?? $this->createDefaultContainer();
        $this->container->set(Config::class, fn() => $this->config);
        $this->bootstrap();
    }

    private function createDefaultContainer(): WritableContainerInterface
    {
        return class_exists(\Solo\Container\Container::class)
            ? new \Solo\Container\Container()
            : throw new RuntimeException(
                'No container provided. Install solophp/container or pass your own WritableContainerInterface.'
            );
    }

    private function bootstrap(): void
    {
        $this->registerCoreServices();
        $this->registerProviders();
        $this->loadRoutes();
    }

    private function registerCoreServices(): void
    {
        $this->container->set(ContainerInterface::class, fn() => $this->container);
        $this->container->set(WritableContainerInterface::class, fn() => $this->container);

        $this->container->set(RouteDispatcher::class, fn(ContainerInterface $c) => new RouteDispatcher(
            $c,
            $c->get(ResponseFactoryInterface::class)
        ));
    }

    private function registerProviders(): void
    {
        foreach ($this->config->providers as $providerClass) {
            (new $providerClass())->register($this->container);
        }
    }

    private function loadRoutes(): void
    {
        $routes = require $this->config->routesPath;
        $routes($this->container->get(RouterInterface::class));
    }

    public function run(?ServerRequestInterface $request = null): void
    {
        $request ??= $this->container->get(ServerRequestInterface::class);
        $dispatcher = $this->container->get(RouteDispatcher::class);

        $pipeline = new MiddlewarePipeline($this->container, $dispatcher);
        $pipeline->addFromArray($this->config->middleware);

        $response = $pipeline->handle($request);

        $emitter = $this->container->get(EmitterInterface::class);
        $emitter->emit($response);
    }
}
