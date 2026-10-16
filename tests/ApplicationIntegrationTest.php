<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Application\Application;
use Solo\Application\Config;
use Solo\Application\RoutingMiddleware;
use Solo\Application\Tests\Fixtures\DelegatingContainer;
use Solo\Application\Tests\Fixtures\FactoryContainer;
use Solo\Application\Tests\Fixtures\FreezingContainer;
use Solo\Application\Tests\Fixtures\HttpServiceProvider;
use Solo\Application\Tests\Fixtures\SpyEmitter;
use Solo\Application\Tests\Fixtures\TraceMiddleware;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\Contracts\Router\RouterInterface;
use Solo\Router\RouteCollector;

/**
 * The whole stack: solophp/container, solophp/router, nyholm/psr7 and the routes in Fixtures/routes.php.
 */
final class ApplicationIntegrationTest extends TestCase
{
    public function testDispatchesControllerWithDecodedParams(): void
    {
        $response = $this->handle('GET', '/users/j%C3%B6rg');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('user jörg', (string) $response->getBody());
    }

    public function testRunsGlobalGroupAndRouteMiddlewareInOrder(): void
    {
        $app = $this->app(middleware: ['trace.global', RoutingMiddleware::class, 'trace.after']);

        $response = $app->handle($this->request('GET', '/admin/reports/daily'));

        $this->assertSame('global>outer>inner>route>after>handler', (string) $response->getBody());
    }

    public function testRouteNameAndAttributesReachHandler(): void
    {
        $response = $this->handle('GET', '/billing/invoices/42');

        $this->assertSame(
            ['name' => 'invoices.show', 'attributes' => ['permission' => 'billing.view', 'plan' => 'pro']],
            json_decode((string) $response->getBody(), true),
        );
    }

    public function testClosureRouteMiddleware(): void
    {
        $response = $this->handle('GET', '/closure');

        $this->assertSame('yes', $response->getHeaderLine('X-Closure'));
        $this->assertSame('global>handler', (string) $response->getBody());
    }

    public function testObjectMethodAndCallableStringHandlers(): void
    {
        $this->assertSame('global>handler', (string) $this->handle('GET', '/object')->getBody());
        $this->assertSame('pong', (string) $this->handle('GET', '/static')->getBody());
    }

    public function testAnswers405WithAllowHeader(): void
    {
        $response = $this->handle('POST', '/users/1');

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, HEAD', $response->getHeaderLine('Allow'));
    }

    public function testAnswers404ForUnknownPath(): void
    {
        $this->assertSame(404, $this->handle('GET', '/missing')->getStatusCode());
    }

    public function testHeadIsAnsweredByGetRoute(): void
    {
        $this->assertSame(200, $this->handle('HEAD', '/users/1')->getStatusCode());
    }

    public function testRunEmitsResponse(): void
    {
        $app = $this->app();
        $app->run($this->request('GET', '/users/1'));

        $emitter = $app->container->get(EmitterInterface::class);
        $this->assertInstanceOf(SpyEmitter::class, $emitter);
        $this->assertSame('user 1', (string) $emitter->emitted?->getBody());
    }

    public function testRouterHoldingTheRoutesIsShared(): void
    {
        $app = $this->app();

        $this->assertNotFalse($app->router->match('GET', '/users/1'));
        $this->assertSame($app->router, $app->container->get(RouterInterface::class));
        $this->assertSame($app->router, $app->container->get(RouteCollector::class));
    }

    public function testRoutesSurviveContainerWithoutSharedInstances(): void
    {
        $app = $this->app(new FactoryContainer());

        $response = $app->handle($this->request('GET', '/users/1'));

        $this->assertSame('user 1', (string) $response->getBody());
    }

    public function testBootsWithContainerThatFreezesResolvedServices(): void
    {
        $app = $this->app(new FreezingContainer());

        $this->assertSame('user 1', (string) $app->handle($this->request('GET', '/users/1'))->getBody());
    }

    public function testRouteMiddlewareResolvesThroughTheApplicationContainer(): void
    {
        // Factories receive the inner container, which does not know "trace.fallback"
        $app = $this->app(new DelegatingContainer(['trace.fallback' => new TraceMiddleware('fallback')]));

        $response = $app->handle($this->request('GET', '/fallback'));

        $this->assertSame('global>fallback>handler', (string) $response->getBody());
    }

    private function handle(string $method, string $uri): ResponseInterface
    {
        return $this->app()->handle($this->request($method, $uri));
    }

    private function request(string $method, string $uri): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest($method, $uri);
    }

    /**
     * @param array<string> $middleware
     */
    private function app(
        ?WritableContainerInterface $container = null,
        array $middleware = ['trace.global', RoutingMiddleware::class],
    ): Application {
        return new Application(
            new Config(
                basePath: dirname(__DIR__),
                routesPath: __DIR__ . '/Fixtures/routes.php',
                providers: [HttpServiceProvider::class],
                middleware: $middleware,
            ),
            $container,
        );
    }
}
