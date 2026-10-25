# Testing

The library has no global state: a test can construct an `Application` with any `WritableContainerInterface` and call `handle()`, which returns the response without emitting it.

## Application tests

This PHPUnit test runs requests through the application from the [quick start](/guide/quick-start):

```php
<?php

namespace App\Tests;

use App\Providers\HttpServiceProvider;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Solo\Application\Application;
use Solo\Application\Config;

final class UserApiTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $basePath = dirname(__DIR__);

        $this->app = new Application(new Config(
            basePath: $basePath,
            routesPath: $basePath . '/app/routes.php',
            providers: [HttpServiceProvider::class],
            middleware: require $basePath . '/app/middleware.php',
        ));
    }

    public function testShowsUser(): void
    {
        $response = $this->app->handle(new ServerRequest('GET', '/users/1'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"id":1,"name":"Ada"}', (string) $response->getBody());
    }

    public function testRejectsOtherMethods(): void
    {
        $response = $this->app->handle(new ServerRequest('DELETE', '/users/1'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, HEAD', $response->getHeaderLine('Allow'));
    }
}
```

- Each test gets a new application, so services do not leak between tests.
- Leave `routesCachePath` unset in tests, so they always run the current `routes.php`.
- The provider registers the request from globals and the emitter as well; `handle()` uses neither.

## Replacing services

Replace a service through `$app->container`, after the constructor has run the providers and before the request that uses it:

```php
$app = new Application($config);
$app->container->set(Mailer::class, fn() => new FakeMailer());

$response = $app->handle(new ServerRequest('POST', '/password-reset'));
```

A service registered before the constructor would be replaced by a provider registering the same ID.

## Routing errors

`handle()` answers routing exceptions nothing caught, so a test sees `404`, `405` or `400` responses even without `RoutingErrorMiddleware`. To assert the exception itself, pass the request to `RoutingMiddleware` from the container. In `UserApiTest`, with `use` statements for `Psr\Http\Server\RequestHandlerInterface`, `Solo\Application\Exception\MethodNotAllowedException` and `Solo\Application\RoutingMiddleware`:

```php
public function testDeleteIsNotAllowed(): void
{
    $this->expectException(MethodNotAllowedException::class);

    $this->app->container->get(RoutingMiddleware::class)->process(
        new ServerRequest('DELETE', '/users/1'),
        $this->createStub(RequestHandlerInterface::class),
    );
}
```

## Routes

`$app->router` is the built router, so route definitions can be checked without requests, again in `UserApiTest`:

```php
public function testUserRoutes(): void
{
    $route = $this->app->router->getRouteByName('users.show');

    $this->assertSame('/users/{id:[0-9]+}', $route?->path);
    $this->assertSame(['GET', 'HEAD'], $this->app->router->getAllowedMethods('/users/1'));
}
```

## Middleware

A middleware is tested on its own with a request handler that records what reaches it. For the `JsonParserMiddleware` from [Middleware](/features/middleware):

```php
<?php

namespace App\Tests;

use App\Middleware\JsonParserMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JsonParserMiddlewareTest extends TestCase
{
    public function testParsesJsonBody(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };

        $request = (new ServerRequest('POST', '/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody(Stream::create('{"name":"Ada"}'));

        (new JsonParserMiddleware())->process($request, $handler);

        $this->assertSame(['name' => 'Ada'], $handler->request?->getParsedBody());
    }
}
```

To run several middleware or ones given by class name, put them in a [`MiddlewarePipeline`](/api/middleware-pipeline) with such a handler as the final one.

## The package's own tests

In a clone of the repository:

```bash
composer test    # phpunit
composer stan    # phpstan
composer cs      # phpcs (PSR-12)
composer check   # all of the above
```
