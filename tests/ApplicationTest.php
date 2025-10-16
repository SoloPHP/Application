<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Solo\Application\Application;
use Solo\Application\CorsHandlerInterface;
use Solo\Contracts\Router\RouterInterface;
use TypeError;

class ApplicationTest extends TestCase
{
    private ContainerInterface $container;
    private ResponseFactoryInterface $responseFactory;
    private Application $application;
    private CorsHandlerInterface $corsHandler;
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->container = Mockery::mock(ContainerInterface::class);
        $this->responseFactory = Mockery::mock(ResponseFactoryInterface::class);
        $this->corsHandler = Mockery::mock(CorsHandlerInterface::class);
        $this->router = Mockery::mock(RouterInterface::class);
        $this->application = new Application(
            $this->router,
            $this->container,
            $this->responseFactory
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructorWithCorsHandler(): void
    {
        $app = new Application(
            $this->router,
            $this->container,
            $this->responseFactory,
            $this->corsHandler
        );
        $this->assertInstanceOf(Application::class, $app);
    }

    public function testAddMiddlewareWithString(): void
    {
        $middleware = Mockery::mock(MiddlewareInterface::class);
        $this->container->shouldReceive('get')
            ->with('TestMiddleware')
            ->once()
            ->andReturn($middleware);

        $this->application->addMiddleware('TestMiddleware');
        $this->assertTrue(true); // No exception thrown
    }

    public function testAddMiddlewareWithObject(): void
    {
        $middleware = Mockery::mock(MiddlewareInterface::class);
        $this->application->addMiddleware($middleware);
        $this->assertTrue(true); // No exception thrown
    }

    public function testAddMiddlewareWithInvalidObject(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage(
            'Solo\Application\Application::addMiddleware(): Argument #1 ($middleware) ' .
            'must be of type Psr\Http\Server\MiddlewareInterface|string, stdClass given'
        );

        // @phpstan-ignore argument.type
        $this->application->addMiddleware(new \stdClass());
    }

    public function testRunWithCorsOptionsRequest(): void
    {
        $app = new Application(
            $this->router,
            $this->container,
            $this->responseFactory,
            $this->corsHandler
        );

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn('OPTIONS');

        $uri = Mockery::mock(UriInterface::class);
        $request->shouldReceive('getUri')->andReturn($uri);

        $this->corsHandler->shouldReceive('shouldApplyCors')
            ->with($request)
            ->andReturn(true);

        $response = Mockery::mock(ResponseInterface::class);
        $this->responseFactory->shouldReceive('createResponse')
            ->with(200)
            ->andReturn($response);

        $this->corsHandler->shouldReceive('addCorsHeaders')
            ->with($response, $request)
            ->andReturn($response);

        $result = $app->run($request);
        $this->assertSame($response, $result);
    }

    public function testRunWithNotFoundRoute(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn('GET');

        $uri = Mockery::mock(UriInterface::class);
        $uri->shouldReceive('getPath')->andReturn('/not-found');
        $request->shouldReceive('getUri')->andReturn($uri);

        $this->router->shouldReceive('match')
            ->with('GET', '/not-found')
            ->andReturn(false);

        $response = Mockery::mock(ResponseInterface::class);
        $this->responseFactory->shouldReceive('createResponse')
            ->with(404)
            ->andReturn($response);

        $result = $this->application->run($request);
        $this->assertSame($response, $result);
    }

    public function testRunWithFoundRoute(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn('GET');

        $uri = Mockery::mock(UriInterface::class);
        $uri->shouldReceive('getPath')->andReturn('/test');
        $request->shouldReceive('getUri')->andReturn($uri);

        $handler = function ($req, $resp, $params) {
            return $resp;
        };

        $match = [
            'handler' => $handler,
            'params' => [],
            'middlewares' => []
        ];

        $this->router->shouldReceive('match')
            ->with('GET', '/test')
            ->andReturn($match);

        $modifiedRequest = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('withAttribute')
            ->with('handler', $handler)
            ->andReturnSelf();
        $request->shouldReceive('withAttribute')
            ->with('params', [])
            ->andReturn($modifiedRequest);

        $modifiedRequest->shouldReceive('getAttribute')
            ->with('handler')
            ->andReturn($handler);
        $modifiedRequest->shouldReceive('getAttribute')
            ->with('params', [])
            ->andReturn([]);

        $response = Mockery::mock(ResponseInterface::class);
        $this->responseFactory->shouldReceive('createResponse')
            ->andReturn($response);

        $result = $this->application->run($request);
        $this->assertSame($response, $result);
    }

    public function testHandleWithNoMiddleware(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $handler = function ($req, $resp, $params) {
            return $resp;
        };
        $request->shouldReceive('getAttribute')
            ->with('handler')
            ->andReturn($handler);
        $request->shouldReceive('getAttribute')
            ->with('params', [])
            ->andReturn([]);

        $response = Mockery::mock(ResponseInterface::class);
        $this->responseFactory->shouldReceive('createResponse')
            ->andReturn($response);

        $result = $this->application->handle($request);
        $this->assertSame($response, $result);
    }

    public function testHandleWithMiddleware(): void
    {
        $middleware = Mockery::mock(MiddlewareInterface::class);
        $this->application->addMiddleware($middleware);

        $request = Mockery::mock(ServerRequestInterface::class);
        $handler = function ($req, $resp, $params) {
            return $resp;
        };
        $request->shouldReceive('getAttribute')
            ->with('handler')
            ->andReturn($handler);
        $request->shouldReceive('getAttribute')
            ->with('params', [])
            ->andReturn([]);

        $response = Mockery::mock(ResponseInterface::class);
        $this->responseFactory->shouldReceive('createResponse')
            ->andReturn($response);

        $middleware->shouldReceive('process')
            ->with($request, $this->application)
            ->andReturn($response);

        $result = $this->application->handle($request);
        $this->assertSame($response, $result);
    }

    public function testRouteDispatcherWithStringHandler(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getAttribute')
            ->with('handler')
            ->andReturn('TestController');
        $request->shouldReceive('getAttribute')
            ->with('params', [])
            ->andReturn([]);

        $response = Mockery::mock(ResponseInterface::class);
        $this->responseFactory->shouldReceive('createResponse')
            ->andReturn($response);

        $controller = function ($req, $resp, $params) {
            return $resp;
        };

        $this->container->shouldReceive('get')
            ->with('TestController')
            ->andReturn($controller);

        $result = $this->application->handle($request);
        $this->assertSame($response, $result);
    }

    public function testRouteDispatcherWithArrayHandler(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getAttribute')
            ->with('handler')
            ->andReturn(['TestController', 'index']);
        $request->shouldReceive('getAttribute')
            ->with('params', [])
            ->andReturn([]);

        $response = Mockery::mock(ResponseInterface::class);
        $this->responseFactory->shouldReceive('createResponse')
            ->andReturn($response);

        $controller = Mockery::mock();
        $controller->shouldReceive('index')
            ->with($request, $response, [])
            ->andReturn($response);

        $this->container->shouldReceive('get')
            ->with('TestController')
            ->andReturn($controller);

        $result = $this->application->handle($request);
        $this->assertSame($response, $result);
    }

    public function testRouteDispatcherWithInvalidHandler(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getAttribute')
            ->with('handler')
            ->andReturn(null);
        $request->shouldReceive('getAttribute')
            ->with('params', [])
            ->andReturn([]);

        $response = Mockery::mock(ResponseInterface::class);
        $this->responseFactory->shouldReceive('createResponse')
            ->andReturn($response);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid route handler.');

        $this->application->handle($request);
    }
}
