<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;
use Solo\Application\Application;
use Solo\Application\Config;
use Solo\Application\RequestAttribute;
use Solo\Application\Tests\Fixtures\HttpServiceProvider;
use Solo\Container\Container;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\Contracts\Router\RouterInterface;
use Solo\Router\RouteCollector;
use UnexpectedValueException;

final class ApplicationTest extends TestCase
{
    /** @var list<string> */
    private array $routesFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->routesFiles as $file) {
            @unlink($file);
        }
    }

    public function testConstructorRegistersConfigAndContainer(): void
    {
        $container = $this->createContainer();
        $config = $this->createConfig();

        $app = new Application($config, $container);

        $this->assertSame($config, $app->config);
        $this->assertSame($container, $app->container);
        $this->assertSame($config, $container->get(Config::class));
        $this->assertSame($container, $container->get(ContainerInterface::class));
    }

    public function testConstructorUsesDefaultContainer(): void
    {
        $app = new Application($this->createConfig(providers: [HttpServiceProvider::class]));

        $this->assertInstanceOf(Container::class, $app->container);
    }

    public function testConstructorRegistersProviders(): void
    {
        $app = new Application($this->createConfig(providers: [HttpServiceProvider::class]), new Container());

        $this->assertInstanceOf(RouteCollector::class, $app->container->get(RouterInterface::class));
    }

    public function testConstructorRejectsProviderWithoutRegisterMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service provider stdClass must have a public register() method');

        new Application($this->createConfig(providers: [\stdClass::class]), $this->createContainer());
    }

    public function testConstructorRejectsProviderWithNonPublicRegisterMethod(): void
    {
        $provider = new class {
            protected function register(WritableContainerInterface $container): void
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must have a public register() method');

        new Application($this->createConfig(providers: [$provider::class]), $this->createContainer());
    }

    public function testConstructorAcceptsProviderThatRegistersThroughCall(): void
    {
        $provider = new class {
            /**
             * @param array{WritableContainerInterface} $arguments
             */
            public function __call(string $name, array $arguments): void
            {
                $arguments[0]->set('from.call', fn() => $name);
            }
        };
        $container = $this->createContainer();

        new Application($this->createConfig(providers: [$provider::class]), $container);

        $this->assertSame('register', $container->get('from.call'));
    }

    public function testConstructorRejectsMissingRoutesFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Routes file not found: /nonexistent/routes.php');

        new Application(new Config('/app', '/nonexistent/routes.php', [], []), $this->createContainer());
    }

    public function testConstructorRejectsRoutesFileThatDoesNotReturnCallable(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must return a callable, int returned');

        new Application($this->createConfig(routes: '<?php return 42;'), $this->createContainer());
    }

    public function testRoutesFileCannotReachApplication(): void
    {
        // The file returns null, which fails to load, if $this leaks into it
        $config = $this->createConfig(routes: '<?php return isset($this) ? null : function ($router): void {};');

        $this->expectNotToPerformAssertions();

        new Application($config, $this->createContainer());
    }

    public function testRoutesFileReceivesContainer(): void
    {
        $container = $this->createContainer();
        $routes = '<?php return fn($router, $container) => $container->set("from.routes", fn() => "yes");';

        new Application($this->createConfig(routes: $routes), $container);

        $this->assertSame('yes', $container->get('from.routes'));
    }

    public function testRelativeRoutesPathIsLookedUpInIncludePath(): void
    {
        $dir = sys_get_temp_dir() . '/include_' . uniqid();
        $file = 'routes_' . uniqid() . '.php';
        mkdir($dir);
        file_put_contents("$dir/$file", '<?php return fn($r, $container) => $container->set("loaded", fn() => true);');
        $includePath = (string) set_include_path($dir . PATH_SEPARATOR . get_include_path());
        $container = $this->createContainer();

        try {
            new Application(new Config('/app', $file, [], []), $container);
        } finally {
            set_include_path($includePath);
            unlink("$dir/$file");
            rmdir($dir);
        }

        $this->assertTrue($container->get('loaded'));
    }

    public function testHandleReturnsResponseWithoutEmitting(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $request = $this->createRequestWithRoute(fn() => $response);

        $app = new Application($this->createConfig(), $this->createContainer());

        $this->assertSame($response, $app->handle($request));
    }

    public function testHandleLetsOtherExceptionsThrough(): void
    {
        $request = $this->createRequestWithRoute(fn() => throw new RuntimeException('Handler failed'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Handler failed');

        (new Application($this->createConfig(), $this->createContainer()))->handle($request);
    }

    public function testRunUsesRequestFromContainerWhenNotProvided(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $request = $this->createRequestWithRoute(fn() => $response);

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->once())->method('emit')->with($this->identicalTo($response));

        $container = $this->createContainer($request, $emitter);

        $app = new Application($this->createConfig(), $container);
        $app->run();
    }

    public function testRunResolvesEmitterAfterHandling(): void
    {
        $default = $this->createMock(EmitterInterface::class);
        $default->expects($this->never())->method('emit');
        $streaming = $this->createMock(EmitterInterface::class);
        $streaming->expects($this->once())->method('emit');
        $container = $this->createContainer(emitter: $default);

        // The handler picks another emitter for its response, e.g. to stream it
        $request = $this->createRequestWithRoute(function ($request, $response) use ($container, $streaming) {
            $container->set(EmitterInterface::class, fn() => $streaming);
            return $response;
        });

        (new Application($this->createConfig(), $container))->run($request);
    }

    public function testRunWithMiddleware(): void
    {
        $modifiedResponse = $this->createStub(ResponseInterface::class);
        $request = $this->createRequestWithRoute(fn($req, $res, $params) => $res);

        $middleware = $this->createStub(MiddlewareInterface::class);
        $middleware->method('process')->willReturn($modifiedResponse);

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->once())->method('emit')->with($this->identicalTo($modifiedResponse));

        $container = $this->createContainer($request, $emitter);
        $container->set('TestMiddleware', fn() => $middleware);

        $app = new Application(
            $this->createConfig(middleware: ['TestMiddleware']),
            $container,
        );
        $app->run($request);
    }

    /**
     * @param array<class-string> $providers
     * @param array<MiddlewareInterface|callable|string> $middleware
     */
    private function createConfig(
        array $providers = [],
        array $middleware = [],
        string $routes = '<?php return function ($router): void {};',
    ): Config {
        $routesFile = sys_get_temp_dir() . '/test_routes_' . uniqid() . '.php';
        file_put_contents($routesFile, $routes);
        $this->routesFiles[] = $routesFile;

        return new Config(
            basePath: '/app',
            routesPath: $routesFile,
            providers: $providers,
            middleware: $middleware,
        );
    }

    private function createContainer(
        ?ServerRequestInterface $request = null,
        ?EmitterInterface $emitter = null,
    ): WritableContainerInterface {
        $container = new Container();
        $container->set(RouterInterface::class, fn() => new RouteCollector());
        $container->set(ResponseFactoryInterface::class, fn() => new Psr17Factory());
        if ($request !== null) {
            $container->set(ServerRequestInterface::class, fn() => $request);
        }
        if ($emitter !== null) {
            $container->set(EmitterInterface::class, fn() => $emitter);
        }
        return $container;
    }

    private function createRequestWithRoute(callable $handler): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/')
            ->withAttribute(RequestAttribute::HANDLER, $handler);
    }
}
