<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Application\Exception\RouteNotFoundException;
use Solo\Application\RequestAttribute;
use Solo\Application\RouteDispatcher;
use Solo\Application\Tests\Fixtures\TestController;
use UnexpectedValueException;

final class RouteDispatcherTest extends TestCase
{
    private ContainerInterface&Stub $container;

    protected function setUp(): void
    {
        $this->container = $this->createStub(ContainerInterface::class);
    }

    public function testHandleThrowsRouteNotFoundWhenNoHandler(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('No route matches GET /');

        $this->dispatch(null);
    }

    public function testNamesAnEmptyPathAsRoot(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessageMatches('#^No route matches GET /$#');

        (new RouteDispatcher($this->container, new Psr17Factory()))
            ->handle((new Psr17Factory())->createServerRequest('GET', 'https://example.com'));
    }

    public function testHandleWithArrayHandlerResolvesControllerFromContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(TestController::class)
            ->willReturn(new TestController());

        $response = $this->dispatch([TestController::class, 'show'], ['id' => '123'], $container);

        $this->assertSame('user 123', (string) $response->getBody());
    }

    public function testHandleWithInvokableHandler(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with('App\\Action')->willReturn($this->action('invoked'));

        $this->assertSame('invoked', (string) $this->dispatch('App\\Action', [], $container)->getBody());
    }

    public function testContainerIdTakesPrecedenceOverFunctionName(): void
    {
        // reset() is a PHP function, but a service registered as "reset" must win
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('reset')->willReturn(true);
        $container->expects($this->once())->method('get')->with('reset')->willReturn($this->action('reset action'));

        $this->assertSame('reset action', (string) $this->dispatch('reset', [], $container)->getBody());
    }

    public function testHandleWithObjectMethodDoesNotUseContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $response = $this->dispatch([new TestController(), 'show'], ['id' => '7'], $container);

        $this->assertSame('user 7', (string) $response->getBody());
    }

    public function testHandleWithCallableStringDoesNotUseContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $response = $this->dispatch(TestController::class . '::ping', [], $container);

        $this->assertSame('pong', (string) $response->getBody());
    }

    public function testHandleWithStaticMethodArrayDoesNotUseContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $response = $this->dispatch([TestController::class, 'ping'], [], $container);

        $this->assertSame('pong', (string) $response->getBody());
    }

    public function testHandleWithCallableHandler(): void
    {
        $received = null;
        $handler = function ($request, $response, $params) use (&$received) {
            $received = [$request, $params];
            return $response->withStatus(201);
        };

        $request = $this->request($handler, ['id' => '42', 'slug' => 'test']);
        $response = (new RouteDispatcher($this->container, new Psr17Factory()))->handle($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([$request, ['id' => '42', 'slug' => 'test']], $received);
    }

    public function testHandleThrowsExceptionForInvalidHandler(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route handler int is not callable');

        $this->dispatch(12345);
    }

    public function testHandleThrowsExceptionForMissingControllerMethod(): void
    {
        $this->container->method('get')->willReturn(new TestController());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route handler ' . TestController::class . '::missing is not callable');

        $this->dispatch([TestController::class, 'missing']);
    }

    public function testHandleThrowsExceptionWhenHandlerDoesNotReturnResponse(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Route handler Closure must return Psr\Http\Message\ResponseInterface, string returned'
        );

        $this->dispatch(fn() => 'oops');
    }

    public function testExceptionNamesAnonymousClassWithoutItsInternalName(): void
    {
        $handler = [new class {
            public function show(): string
            {
                return 'oops';
            }
        }, 'show'];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Route handler class@anonymous::show must return');

        $this->dispatch($handler);
    }

    /**
     * An invokable handler that writes $body.
     */
    private function action(string $body): object
    {
        return new class ($body) {
            public function __construct(private readonly string $body)
            {
            }

            /**
             * @param array<string, string> $params
             */
            public function __invoke(
                ServerRequestInterface $request,
                ResponseInterface $response,
                array $params
            ): ResponseInterface {
                $response->getBody()->write($this->body);
                return $response;
            }
        };
    }

    /**
     * @param array<string, string> $params
     */
    private function dispatch(
        mixed $handler,
        array $params = [],
        ?ContainerInterface $container = null,
    ): ResponseInterface {
        $dispatcher = new RouteDispatcher($container ?? $this->container, new Psr17Factory());

        return $dispatcher->handle($this->request($handler, $params));
    }

    /**
     * @param array<string, string> $params
     */
    private function request(mixed $handler, array $params): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/')
            ->withAttribute(RequestAttribute::HANDLER, $handler)
            ->withAttribute(RequestAttribute::PARAMS, $params);
    }
}
