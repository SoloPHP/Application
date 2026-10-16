<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\Contracts\Router\RouterInterface;
use Solo\Router\RouteCollector;

final class HttpServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(ResponseFactoryInterface::class, fn() => new Psr17Factory());
        $container->set(RouterInterface::class, fn() => new RouteCollector());
        $container->set(RouteCollector::class, fn(ContainerInterface $c) => $c->get(RouterInterface::class));
        $container->set(EmitterInterface::class, fn() => new SpyEmitter());

        foreach (['global', 'after', 'outer', 'inner', 'route'] as $name) {
            $container->set("trace.$name", fn() => new TraceMiddleware($name));
        }
    }
}
