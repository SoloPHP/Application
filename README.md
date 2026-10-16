# Solo Application

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![License](https://img.shields.io/packagist/l/solophp/application.svg)](https://github.com/solophp/application/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![Tests](https://github.com/solophp/application/actions/workflows/tests.yml/badge.svg)](https://github.com/solophp/application/actions/workflows/tests.yml)

Application bootstrap with PSR-15 middleware pipeline.

A lightweight, PSR-compliant application kernel that wires together your container, router, middleware, and HTTP layer with zero framework lock-in.

## Features

- PSR-15 middleware pipeline with lazy resolution from the container
- Built-in `RoutingMiddleware`: route and group middleware, `405 Method Not Allowed` with an `Allow` header, route name and attributes on the request
- `Application` is itself a PSR-15 request handler: `handle()` returns the response, `run()` emits it
- PSR-11 container integration with writable extension
- Service providers for dependency registration
- Handlers: controller methods, invokable classes, any callable
- Built only on PSR interfaces and `solophp/contracts`

## Installation

```bash
composer require solophp/application
```

## Requirements

- PHP 8.2+
- A PSR-7/PSR-17 implementation (e.g., [nyholm/psr7](https://github.com/nyholm/psr7))
- A container implementing `Solo\Contracts\Container\WritableContainerInterface`
- An HTTP emitter implementing `Solo\Contracts\Http\EmitterInterface`
- A router implementing `Solo\Contracts\Router\RouterInterface` (e.g., [solophp/router](https://github.com/solophp/router); 3.3+ for `405` responses, 3.4+ for route attributes)

## Dependencies

| Dependency | Purpose |
|------------|---------|
| psr/container | Container interface |
| psr/http-message | HTTP messages |
| psr/http-server-handler | Request handler |
| psr/http-server-middleware | Middleware |
| psr/http-factory | Response factory |
| solophp/contracts (^1.5) | `WritableContainerInterface`, `EmitterInterface`, `RouterInterface`, `AllowedMethodsInterface` |

## Quick Start

```php
// public/index.php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Solo\Application\Application;
use Solo\Application\Config;

$basePath = dirname(__DIR__);

$config = new Config(
    basePath: $basePath,
    routesPath: $basePath . '/app/routes.php',
    providers: require $basePath . '/app/providers.php',
    middleware: require $basePath . '/app/middleware.php',
);

$app = new Application($config);
$app->run();
```

## How It Works

`Application` separates *bootstrap* (one-time, in the constructor) from *handling* (per request):

```
new Application($config)
      │
      ├── 1. Create / accept container
      ├── 2. Register core services (Container, RouteDispatcher, RoutingMiddleware)
      ├── 3. Run providers          ← your dependencies
      └── 4. Load routes            ← require $config->routesPath

$app->run($request?)  =  emit($app->handle($request))
      │
      ▼
ServerRequest (provided or resolved from container)
      │
      ▼
MiddlewarePipeline ── [mw 1] → [mw 2] → … → [RoutingMiddleware] ── no route: 404 / 405
                                                     │
                                                     ▼
                     request attributes: handler, params, route_name, route_attributes
                                                     │
                                                     ▼
                                     route middleware (groups, then route)
                                                     │
                                                     ▼
RouteDispatcher  →  Controller / invokable / callable
      │
      ▼
ResponseInterface  →  returned by handle(), emitted by run()
```

`RoutingMiddleware` writes these request attributes; `RouteDispatcher` reads `handler` and `params`. The names are available as constants on `Solo\Application\RequestAttribute`:

| Attribute | Constant | Type |
|-----------|----------|------|
| `handler` | `RequestAttribute::HANDLER` | `array{class-string,string} \| string \| callable` |
| `params` | `RequestAttribute::PARAMS` | `array<string,string>`, percent-decoded |
| `route_name` | `RequestAttribute::ROUTE_NAME` | `string \| null` |
| `route_attributes` | `RequestAttribute::ROUTE_ATTRIBUTES` | `array<string,mixed>` |

If `handler` is `null`, the dispatcher returns a `404` response from the response factory.

## Configuration

`Config` is an immutable DTO:

```php
new Config(
    basePath:   '/var/www/app',
    routesPath: '/var/www/app/routes.php',
    providers:  [HttpServiceProvider::class, DatabaseServiceProvider::class],
    middleware: [ErrorHandler::class, JsonParser::class, RoutingMiddleware::class],
);
```

`Config` is registered in the container, so your services can read `basePath`; the kernel itself does not use it.

### `routes.php`

Returns a callable that receives the router registered as `RouterInterface` and the container. Type-hint `RouteCollector` to use the fluent API of `solophp/router`:

```php
<?php

use App\Controller\UserController;
use App\Middleware\AuthMiddleware;
use Psr\Container\ContainerInterface;
use Solo\Router\RouteCollector;

return function (RouteCollector $r, ContainerInterface $container): void {
    $r->get('/api/users', [UserController::class, 'index'])->name('users.index');
    $r->get('/api/users/{id}', [UserController::class, 'show'])->name('users.show');

    $r->group('/api/admin', function (RouteCollector $r): void {
        $r->post('/users', [UserController::class, 'store'])
            ->attribute('permission', 'users.create');
    }, [AuthMiddleware::class]);
};
```

The file is loaded in its own scope, so `$this` is not available: reach services or `Config` through the container instead. `routesPath` is found the way `require` finds files, so a relative path is looked up in the include path. A missing file throws `RuntimeException`, a file that does not return a callable throws `UnexpectedValueException`.

### `providers.php`

```php
<?php

return [
    HttpServiceProvider::class,
    DatabaseServiceProvider::class,
    LoggingServiceProvider::class,
];
```

### `middleware.php`

Order matters — middleware execute top-to-bottom on the way in, bottom-to-top on the way out:

```php
<?php

use Solo\Application\RoutingMiddleware;

return [
    ErrorHandlerMiddleware::class,
    CorsMiddleware::class,
    JsonParserMiddleware::class,
    RoutingMiddleware::class, // last: matches the route and runs its middleware
];
```

## Required Services

Your providers must register these into the container:

| Service ID | Interface |
|-----------|-----------|
| `Psr\Http\Message\ResponseFactoryInterface` | PSR-17 response factory |
| `Psr\Http\Message\ServerRequestInterface` | The incoming request (used when `run()` is called without an argument) |
| `Solo\Contracts\Router\RouterInterface` | Router; `routes.php` is called with this |
| `Solo\Contracts\Http\EmitterInterface` | Sends the response back to the client (used by `run()`) |

`Application` keeps the router it loaded the routes into as `$app->router` and hands that instance to `RoutingMiddleware` directly, so routing works whether or not your container shares instances. Services that need the router themselves, such as a URL generator, get it from the container, which shares it as `WritableContainerInterface` expects. If your container rebuilds services, for example after `Container::reset()`, register the loaded router for them once after construction: `$container->set(RouterInterface::class, fn() => $app->router)`.

`ContainerInterface`, `WritableContainerInterface`, `Config`, `RouteDispatcher` and `RoutingMiddleware` are registered by `Application` itself.

Any middleware or controller class IDs referenced from configuration must also resolve.

## Service Providers

Providers are plain classes with a public `register()` method. They are instantiated once during bootstrap; a class without one throws `InvalidArgumentException`:

```php
<?php

namespace App\Providers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\Contracts\Router\RouterInterface;
use Solo\HttpEmitter\Emitter;
use Solo\Router\RouteCollector;

final class HttpServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(Psr17Factory::class, fn() => new Psr17Factory());

        $container->set(
            ResponseFactoryInterface::class,
            fn(ContainerInterface $c) => $c->get(Psr17Factory::class),
        );

        $container->set(ServerRequestInterface::class, function (ContainerInterface $c) {
            $factory = $c->get(Psr17Factory::class);
            return (new ServerRequestCreator($factory, $factory, $factory, $factory))
                ->fromGlobals();
        });

        $container->set(RouterInterface::class, fn() => new RouteCollector());

        // Alias, not a second factory: services that type-hint RouteCollector (e.g. for URL generation)
        // get the same router as RouterInterface
        $container->set(RouteCollector::class, fn(ContainerInterface $c) => $c->get(RouterInterface::class));

        $container->set(EmitterInterface::class, fn() => new Emitter());
    }
}
```

## Middleware

A middleware is any of:

- a class-string or other container ID, resolved *lazily* — only when the request reaches it;
- a `Psr\Http\Server\MiddlewareInterface` instance;
- a callable with the signature of `process()`: `fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`. A string the container does not have but PHP can call, such as `'Class::staticMethod'`, counts as one. A callable that returns anything but a `ResponseInterface` throws `UnexpectedValueException`.

```php
<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JsonParserMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (str_starts_with($request->getHeaderLine('Content-Type'), 'application/json')) {
            $data = json_decode((string) $request->getBody(), true);
            // Invalid JSON and scalars such as `42` leave no parsed body: PSR-7 allows only arrays and objects
            $request = $request->withParsedBody(is_array($data) ? $data : null);
        }

        return $handler->handle($request);
    }
}
```

The three forms can be mixed:

```php
$config = new Config(
    /* … */,
    middleware: [
        new SomeMiddleware($dependency),
        fn($request, $handler) => $handler->handle($request)->withHeader('X-Powered-By', 'Solo'),
        AuthMiddleware::class,
    ],
);
```

`MiddlewarePipeline` keeps no per-request state: the same pipeline can handle any number of requests, and a middleware may call `$handler->handle()` more than once (e.g. to retry).

## Routing

Add `RoutingMiddleware::class` to the middleware list. For each request it:

1. matches the method and the path as the client sent it, with only percent-encoded non-ASCII bytes decoded: `/caf%C3%A9` matches a `/café` route, while escapes of ASCII characters stay, so `/%61dmin` does not match `/admin` and `%2F` never splits a segment;
2. if no route matches, answers an empty `405 Method Not Allowed` with an `Allow` header when the path exists for other methods, and `404` otherwise; `405` needs a router that implements `Solo\Contracts\Router\AllowedMethodsInterface` (`solophp/router` 3.5+) or has a public `getAllowedMethods()` (3.3+). To customize these responses, wrap them with an error-handling middleware that inspects the status code;
3. decodes the route parameters, answering `400 Bad Request` when one decodes to a control character such as `%00` or `%0A`, and writes `handler`, `params`, `route_name` and `route_attributes` to the request;
4. runs the route's middleware — group middleware from the outermost group in, then the route's own — and passes the request on to the rest of the pipeline and the dispatcher.

Route middleware use the same three forms as global middleware; `solophp/router` accepts class-strings and callables.

Middleware before `RoutingMiddleware` see the same path structure as the router, so a check on `$request->getUri()->getPath()` cannot be bypassed with escapes such as `/%61dmin`. Parameters, though, are decoded: `/files/a%2Fb` gives `name` = `a/b` for `/files/{name}`, so validate parameters before building file paths from them.

Middleware listed after `RoutingMiddleware` run after the route middleware and can read the route. For example, a permission check driven by route attributes:

```php
use Solo\Application\RequestAttribute;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ResponseFactoryInterface $responses,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $permission = $request->getAttribute(RequestAttribute::ROUTE_ATTRIBUTES, [])['permission'] ?? null;

        if ($permission !== null && !$this->gate->allows($request, $permission)) {
            return $this->responses->createResponse(403);
        }

        return $handler->handle($request);
    }
}

// middleware.php
return [ErrorHandlerMiddleware::class, RoutingMiddleware::class, PermissionMiddleware::class];
```

`HEAD` requests are answered by `GET` routes (`solophp/router` 3.2+); PHP itself does not send a response body for `HEAD`.

You can still write your own routing middleware: set `RequestAttribute::HANDLER` and the decoded `RequestAttribute::PARAMS` for the dispatcher, and run route middleware with `(new MiddlewarePipeline($container, $handler))->addFromArray($middlewares)->handle($request)`.

## Route Handlers

`RouteDispatcher` accepts three handler forms. Each receives `(ServerRequestInterface $request, ResponseInterface $response, array $params)` and must return a `ResponseInterface`; anything else throws `UnexpectedValueException`, and a handler that cannot be called throws `InvalidArgumentException`.

### Controller method

The classic `[Class::class, 'method']` form. The class is resolved from the container — constructor dependencies are injected by your DI. A static method is called without an instance:

```php
final class UserController
{
    public function __construct(private readonly UserRepository $users) {}

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $user = $this->users->find($params['id']);
        $response->getBody()->write(json_encode($user));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

// routes.php
$r->get('/users/{id}', [UserController::class, 'show']);
```

### Invokable class

A class-string (or any other container ID). The container resolves it and the dispatcher calls `__invoke()`:

```php
final class HealthCheck
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $response->getBody()->write('ok');
        return $response;
    }
}

$r->get('/health', HealthCheck::class);
```

### Callable

Closures, `[$object, 'method']`, function names and `'Class::staticMethod'` strings are called as they are, without the container — useful for trivial routes and tests. The container comes first, though: a string is called directly only when the container does not have it, so a service registered as `log` wins over PHP's `log()` function.

```php
$r->get('/ping', function ($request, $response, $params) {
    $response->getBody()->write('pong');
    return $response;
});
```

## Handling Without Emitting

`Application` implements `Psr\Http\Server\RequestHandlerInterface`. `handle()` runs the pipeline and returns the response without touching the emitter — use it in tests, or in worker runtimes (RoadRunner, FrankenPHP worker mode) that create the application once and pass every request to it:

```php
$app = new Application($config);

$response = $app->handle($request); // no EmitterInterface needed
```

Called without an argument, `run()` resolves `ServerRequestInterface` from the container, which a shared-instance container builds only once — in a long-running process, pass each request explicitly. `run()` resolves the emitter after handling, so the request can still replace it, for example to stream the response.

## Custom Container

By default the application creates a `Solo\Container\Container` instance. You can supply your own:

```php
$container = new MyContainer();
// pre-register services if needed
$app = new Application($config, $container);
```

The container must implement `Solo\Contracts\Container\WritableContainerInterface` (`get()` + `set()`). `Application` calls `set()` only for services it has not resolved yet, so containers that refuse to replace a resolved service, like Pimple, work too.

## Components

| Class | Description |
|-------|-------------|
| `Application` | Bootstraps the application; PSR-15 handler (`handle()`) and `run()` to emit |
| `Config` | Immutable configuration DTO |
| `MiddlewarePipeline` | Reusable PSR-15 middleware pipeline (acts as both pipeline and `RequestHandlerInterface`) |
| `RoutingMiddleware` | Matches the route, answers 404/405, runs route middleware |
| `RouteDispatcher` | Resolves and invokes the matched route handler |
| `RequestAttribute` | Names of the request attributes used for routing |

## Testing

The library has no global state — tests can construct an `Application` with any `WritableContainerInterface` and call `handle()`:

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Solo\Application\Application;
use Solo\Application\Config;
use Solo\Application\RoutingMiddleware;

$config = new Config(
    basePath:   __DIR__,
    routesPath: __DIR__ . '/routes.php',
    providers:  [HttpServiceProvider::class], // the provider from Service Providers above
    middleware: [RoutingMiddleware::class],
);

$app = new Application($config);
$response = $app->handle((new Psr17Factory())->createServerRequest('GET', '/users/1'));
```

Run the bundled test suite:

```bash
composer test    # phpunit
composer stan    # phpstan
composer cs      # phpcs (PSR-12)
composer check   # all of the above
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
