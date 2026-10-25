<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Solo\Application\Exception\InvalidRouteParameterException;
use Solo\Application\Exception\MethodNotAllowedException;
use Solo\Application\Exception\RouteNotFoundException;
use Solo\Application\Exception\RoutingException;
use Solo\Application\RequestAttribute;
use Solo\Application\RoutingMiddleware;
use Solo\Application\Tests\Fixtures\RecordingHandler;
use Solo\Application\Tests\Fixtures\TestController;
use Solo\Application\Tests\Fixtures\TraceMiddleware;
use Solo\Container\Container;
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

    public function testUnnamedRouteHasNullNameAndNoAttributes(): void
    {
        $this->router->get('/', 'App\\Action');

        $this->process('GET', '/');

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
        $this->assertInstanceOf(RouteNotFoundException::class, $this->rejection('GET', '/%61dmin/users'));

        // An escaped slash stays inside its segment and is decoded with the parameter
        $this->assertSame(200, $this->process('GET', '/files/a%2Fb')->getStatusCode());
        $this->assertSame(['name' => 'a/b'], $this->next->last()->getAttribute(RequestAttribute::PARAMS));
    }

    public function testEscapedLineBreakDoesNotSatisfyParameterPattern(): void
    {
        $this->router->get('/users/{id:\d+}', [TestController::class, 'show']);

        $this->assertInstanceOf(RouteNotFoundException::class, $this->rejection('GET', '/users/1%0A'));
    }

    public function testParameterDecodingToControlCharacterIsRejected(): void
    {
        $this->router->get('/files/{name}', [TestController::class, 'show']);

        $rejection = $this->rejection('GET', '/files/a%00b');

        $this->assertInstanceOf(InvalidRouteParameterException::class, $rejection);
        $this->assertSame(
            'Route parameter "name" in GET /files/a%00b decodes to a control character',
            $rejection->getMessage(),
        );
        $this->assertInstanceOf(InvalidRouteParameterException::class, $this->rejection('GET', '/files/a%0A'));
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

    public function testThrowsRouteNotFoundWhenNothingMatches(): void
    {
        $this->router->get('/users', [TestController::class, 'show']);

        $rejection = $this->rejection('GET', '/posts');

        $this->assertInstanceOf(RouteNotFoundException::class, $rejection);
        $this->assertSame('No route matches GET /posts', $rejection->getMessage());
        $this->assertSame([], $this->next->requests);
    }

    public function testThrowsMethodNotAllowedWhenOnlyTheMethodDiffers(): void
    {
        $this->router->get('/users', [TestController::class, 'show']);
        $this->router->post('/users', [TestController::class, 'show']);

        $rejection = $this->rejection('DELETE', '/users');

        $this->assertInstanceOf(MethodNotAllowedException::class, $rejection);
        $this->assertSame(['GET', 'HEAD', 'POST'], $rejection->getAllowedMethods());
        $this->assertSame(
            'Method DELETE is not allowed for /users; allowed: GET, HEAD, POST',
            $rejection->getMessage(),
        );
        $this->assertSame([], $this->next->requests);
    }

    public function testRejectionNamesThePathAsSent(): void
    {
        $this->assertSame(
            'No route matches GET /caf%C3%A9/%FF',
            $this->rejection('GET', '/caf%C3%A9/%FF')->getMessage(),
        );
        $this->assertSame('No route matches GET /', $this->rejection('GET', 'https://example.com')->getMessage());
        $this->assertSame('No route matches GET /posts', $this->rejection('GET', 'posts')->getMessage());
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

    private function process(string $method, string $uri): ResponseInterface
    {
        $middleware = new RoutingMiddleware($this->router->build(), $this->container);

        return $middleware->process((new Psr17Factory())->createServerRequest($method, $uri), $this->next);
    }

    private function rejection(string $method, string $uri): RoutingException
    {
        try {
            $this->process($method, $uri);
        } catch (RoutingException $e) {
            return $e;
        }

        $this->fail("$method $uri was not rejected");
    }
}
