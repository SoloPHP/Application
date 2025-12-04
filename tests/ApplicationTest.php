<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Solo\Application\Application;
use Solo\Application\Config;
use Solo\Application\RouteDispatcher;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\Contracts\Router\RouterInterface;

final class ApplicationTest extends TestCase
{
    private string $routesFile;

    protected function setUp(): void
    {
        $this->routesFile = sys_get_temp_dir() . '/test_routes_' . uniqid() . '.php';
        file_put_contents($this->routesFile, '<?php return function($router) {};');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->routesFile)) {
            unlink($this->routesFile);
        }
    }

    public function testConstructorSetsConfigInContainer(): void
    {
        $container = $this->createWritableContainer();
        $config = $this->createConfig();

        $app = new Application($config, $container);

        $this->assertSame($config, $app->config);
        $this->assertSame($container, $app->container);
    }

    public function testConstructorUsesDefaultContainer(): void
    {
        $config = new Config(
            basePath: '/app',
            routesPath: $this->routesFile,
            providers: [TestServiceProvider::class],
            middleware: [],
        );

        $app = new Application($config);

        $this->assertInstanceOf(\Solo\Container\Container::class, $app->container);
    }

    public function testConstructorRegistersProviders(): void
    {
        $container = $this->createWritableContainer();

        $providerFile = sys_get_temp_dir() . '/test_provider_' . uniqid() . '.php';
        $markerFile = sys_get_temp_dir() . '/provider_called_' . uniqid();

        file_put_contents($providerFile, '<?php
            return new class {
                public function register($container): void {
                    file_put_contents("' . $markerFile . '", "1");
                }
            };
        ');

        $provider = require $providerFile;
        $providerClass = get_class($provider);

        $config = new Config(
            basePath: '/app',
            routesPath: $this->routesFile,
            providers: [$providerClass],
            middleware: [],
        );

        new Application($config, $container);

        $this->assertFileExists($markerFile);

        unlink($providerFile);
        unlink($markerFile);
    }

    public function testRunExecutesMiddlewarePipelineAndEmitsResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['handler', null, fn($req, $res, $params) => $res],
            ['params', [], []],
        ]);

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->once())->method('emit')->with($this->isInstanceOf(ResponseInterface::class));

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $router = $this->createMock(RouterInterface::class);

        $services = [
            RouterInterface::class => $router,
            ResponseFactoryInterface::class => $responseFactory,
            ServerRequestInterface::class => $request,
            EmitterInterface::class => $emitter,
        ];

        $container = $this->createMock(WritableContainerInterface::class);
        $container->method('get')->willReturnCallback(function ($id) use ($services, $container, $responseFactory) {
            if ($id === ContainerInterface::class || $id === WritableContainerInterface::class) {
                return $container;
            }
            if ($id === RouteDispatcher::class) {
                return new RouteDispatcher($container, $responseFactory);
            }
            return $services[$id] ?? null;
        });

        $config = $this->createConfig();
        $app = new Application($config, $container);
        $app->run($request);
    }

    public function testRunUsesRequestFromContainerWhenNotProvided(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['handler', null, fn($req, $res, $params) => $res],
            ['params', [], []],
        ]);

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->once())->method('emit');

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $router = $this->createMock(RouterInterface::class);

        $services = [
            RouterInterface::class => $router,
            ResponseFactoryInterface::class => $responseFactory,
            ServerRequestInterface::class => $request,
            EmitterInterface::class => $emitter,
        ];

        $container = $this->createMock(WritableContainerInterface::class);
        $container->method('get')->willReturnCallback(function ($id) use ($services, $container, $responseFactory) {
            if ($id === ContainerInterface::class || $id === WritableContainerInterface::class) {
                return $container;
            }
            if ($id === RouteDispatcher::class) {
                return new RouteDispatcher($container, $responseFactory);
            }
            return $services[$id] ?? null;
        });

        $config = $this->createConfig();
        $app = new Application($config, $container);
        $app->run();
    }

    public function testRunWithMiddleware(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $modifiedResponse = $this->createMock(ResponseInterface::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['handler', null, fn($req, $res, $params) => $res],
            ['params', [], []],
        ]);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturnCallback(function ($request, $handler) use ($modifiedResponse) {
            return $modifiedResponse;
        });

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->once())->method('emit')->with($modifiedResponse);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $router = $this->createMock(RouterInterface::class);

        $services = [
            RouterInterface::class => $router,
            ResponseFactoryInterface::class => $responseFactory,
            ServerRequestInterface::class => $request,
            EmitterInterface::class => $emitter,
            'TestMiddleware' => $middleware,
        ];

        $container = $this->createMock(WritableContainerInterface::class);
        $container->method('get')->willReturnCallback(function ($id) use ($services, $container, $responseFactory) {
            if ($id === ContainerInterface::class || $id === WritableContainerInterface::class) {
                return $container;
            }
            if ($id === RouteDispatcher::class) {
                return new RouteDispatcher($container, $responseFactory);
            }
            return $services[$id] ?? null;
        });

        $config = new Config(
            basePath: '/app',
            routesPath: $this->routesFile,
            providers: [],
            middleware: ['TestMiddleware'],
        );

        $app = new Application($config, $container);
        $app->run($request);
    }

    private function createConfig(): Config
    {
        return new Config(
            basePath: '/app',
            routesPath: $this->routesFile,
            providers: [],
            middleware: [],
        );
    }

    private function createWritableContainer(): WritableContainerInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);

        $container = $this->createMock(WritableContainerInterface::class);
        $container->method('get')->willReturnCallback(function ($id) use ($router, $responseFactory) {
            return match ($id) {
                RouterInterface::class => $router,
                ResponseFactoryInterface::class => $responseFactory,
                default => null,
            };
        });

        return $container;
    }
}
