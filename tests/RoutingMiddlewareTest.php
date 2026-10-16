<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Solo\Application\RequestAttribute;
use Solo\Application\RoutingMiddleware;
use Solo\Application\Tests\Fixtures\ForwardingRouter;
use Solo\Application\Tests\Fixtures\RecordingHandler;
use Solo\Application\Tests\Fixtures\TestController;
use Solo\Application\Tests\Fixtures\TraceMiddleware;
use Solo\Container\Container;
use Solo\Contracts\Router\AllowedMethodsInterface;
use Solo\Contracts\Router\RouterInterface;
use Solo\Router\RouteCollector;

final class RoutingMiddlewareTest extends TestCase
{
    private RouteCollector $router;
    private Container $container;
    private RecordingHandler $next;

    protected function setUp(): void
    {
        $this->router = new RouteCollector();
        $this->container = new Container();
        $this->next = new RecordingHandler();
    }

    public function testStoresMatchedRouteInRequestAttributes(): void
    {
        $this->router->group('/billing', function (RouteCollector $r): void {
            $r->get('/invoices/{id}', [TestController::class, 'show'])
                ->name('invoices.show')
                ->attribute('plan', 'pro');
        }, attributes: ['permission' => 'billing.view']);

        $this->process('GET', '/billing/invoices/42');

        $request = $this->next->last();
        $this->assertSame([TestController::class, 'show'], $request->getAttribute(RequestAttribute::HANDLER));
        $this->assertSame(['id' => '42'], $request->getAttribute(RequestAttribute::PARAMS));
        $this->assertSame('invoices.show', $request->getAttribute(RequestAttribute::ROUTE_NAME));
        $this->assertSame(
            ['permission' => 'billing.view', 'plan' => 'pro'],
            $request->getAttribute(RequestAttribute::ROUTE_ATTRIBUTES),
        );
    }

    public function testDefaultsNameAndAttributesForRoutersWithoutThem(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn(['handler' => 'App\\Action', 'params' => [], 'middlewares' => []]);

        $this->process('GET', '/', $router);

        $this->assertSame([
            RequestAttribute::HANDLER => 'App\\Action',
            RequestAttribute::PARAMS => [],
            RequestAttribute::ROUTE_NAME => null,
            RequestAttribute::ROUTE_ATTRIBUTES => [],
        ], $this->next->last()->getAttributes());
    }

    public function testMatchesNonAsciiPathAndDecodesParameters(): void
    {
        $this->router->get('/café/{name}', [TestController::class, 'show']);

        $this->assertSame(200, $this->process('GET', '/caf%C3%A9/j%C3%B6rg%20m')->getStatusCode());
        $this->assertSame(['name' => 'jörg m'], $this->next->last()->getAttribute(RequestAttribute::PARAMS));
        $this->assertSame(200, $this->process('GET', '/caf%c3%a9/x')->getStatusCode(), 'Lowercase hex is decoded too');
    }

    public function testEscapedAsciiIsMatchedAsSent(): void
    {
        $this->router->get('/admin/users', [TestController::class, 'show']);
        $this->router->get('/files/{dir}/{name}', [TestController::class, 'show']);
        $this->router->get('/files/{name}', [TestController::class, 'show']);

        // A guard that checks the raw path sees "/%61dmin" as well, so it cannot be bypassed this way
        $this->assertSame(404, $this->process('GET', '/%61dmin/users')->getStatusCode());

        // An escaped slash stays inside its segment and is decoded with the parameter
        $this->assertSame(200, $this->process('GET', '/files/a%2Fb')->getStatusCode());
        $this->assertSame(['name' => 'a/b'], $this->next->last()->getAttribute(RequestAttribute::PARAMS));
    }

    public function testEscapedLineBreakDoesNotSatisfyParameterPattern(): void
    {
        $this->router->get('/users/{id:\d+}', [TestController::class, 'show']);

        $this->assertSame(404, $this->process('GET', '/users/1%0A')->getStatusCode());
    }

    public function testParameterDecodingToControlCharacterIsRejected(): void
    {
        $this->router->get('/files/{name}', [TestController::class, 'show']);

        $this->assertSame(400, $this->process('GET', '/files/a%00b')->getStatusCode());
        $this->assertSame(400, $this->process('GET', '/files/a%0A')->getStatusCode());
        $this->assertSame([], $this->next->requests);
    }

    public function testEmptyPathMatchesRoot(): void
    {
        $this->router->get('/', [TestController::class, 'show']);

        $this->assertSame(200, $this->process('GET', 'https://example.com')->getStatusCode());
    }

    public function testHeadIsAnsweredByGetRoute(): void
    {
        $this->router->get('/users', [TestController::class, 'show']);

        $this->assertSame(200, $this->process('HEAD', '/users')->getStatusCode());
        $this->assertSame(
            [TestController::class, 'show'],
            $this->next->last()->getAttribute(RequestAttribute::HANDLER),
        );
    }

    public function testReturns404WhenNothingMatches(): void
    {
        $this->router->get('/users', [TestController::class, 'show']);

        $response = $this->process('GET', '/posts');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Allow'));
        $this->assertSame([], $this->next->requests);
    }

    public function testReturns405WithAllowHeaderWhenOnlyTheMethodDiffers(): void
    {
        $this->router->get('/users', [TestController::class, 'show']);
        $this->router->post('/users', [TestController::class, 'show']);

        $response = $this->process('DELETE', '/users');

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, HEAD, POST', $response->getHeaderLine('Allow'));
        $this->assertSame([], $this->next->requests);
    }

    public function testReturns404WhenRouterCannotListAllowedMethods(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn(false);

        $this->assertSame(404, $this->process('DELETE', '/users', $router)->getStatusCode());
    }

    public function testAsksRouterImplementingTheContractForAllowedMethods(): void
    {
        /** @var RouterInterface&AllowedMethodsInterface&Stub $router */
        $router = $this->createStubForIntersectionOfInterfaces(
            [RouterInterface::class, AllowedMethodsInterface::class],
        );
        $router->method('match')->willReturn(false);
        $router->method('getAllowedMethods')->willReturn(['GET', 'HEAD']);

        $response = $this->process('POST', '/users', $router);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame(['GET', 'HEAD'], $response->getHeader('Allow'));
    }

    public function testFallsBackToAllowedMethodsTheRouterForwards(): void
    {
        $this->router->get('/users', [TestController::class, 'show']);

        $decorator = new ForwardingRouter($this->router);

        $this->assertSame('GET, HEAD', $this->process('DELETE', '/users', $decorator)->getHeaderLine('Allow'));
    }

    public function testIgnoresAllowedMethodsTheRouterDoesNotExpose(): void
    {
        $router = new class implements RouterInterface {
            /**
             * @param callable|array<mixed>|string $handler
             * @param array<mixed> $options
             */
            public function addRoute(
                string $method,
                string $path,
                callable|array|string $handler,
                array $options = []
            ): object {
                return $this;
            }

            /**
             * @return array{handler: string, params: array<string, string>, middlewares: list<string>}|false
             */
            public function match(string $method, string $uri): array|false
            {
                return false;
            }

            /**
             * @return list<string>
             */
            protected function getAllowedMethods(string $uri): array
            {
                return ['GET'];
            }
        };

        $this->assertSame(404, $this->process('DELETE', '/users', $router)->getStatusCode());
    }

    public function testRunsGroupMiddlewareThenRouteMiddlewareBeforePassingOn(): void
    {
        foreach (['outer', 'inner', 'route'] as $name) {
            $this->container->set($name, fn() => new TraceMiddleware($name));
        }

        $this->router->group('/admin', function (RouteCollector $r): void {
            $r->group('/reports', function (RouteCollector $r): void {
                $r->get('/daily', [TestController::class, 'trace'])->middleware('route');
            }, ['inner']);
        }, ['outer']);

        $this->process('GET', '/admin/reports/daily');

        $this->assertSame(['outer', 'inner', 'route'], TraceMiddleware::trace($this->next->last()));
    }

    public function testRouteMiddlewareCanStopTheRequest(): void
    {
        $this->router->get('/secret', [TestController::class, 'show'])
            ->middleware(fn(): ResponseInterface => (new Psr17Factory())->createResponse(403));

        $this->assertSame(403, $this->process('GET', '/secret')->getStatusCode());
        $this->assertSame([], $this->next->requests);
    }

    private function process(string $method, string $uri, ?RouterInterface $router = null): ResponseInterface
    {
        $middleware = new RoutingMiddleware($router ?? $this->router, $this->container, new Psr17Factory());

        return $middleware->process((new Psr17Factory())->createServerRequest($method, $uri), $this->next);
    }
}
