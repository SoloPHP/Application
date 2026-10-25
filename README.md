# Solo Application

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![License](https://img.shields.io/packagist/l/solophp/application.svg)](https://github.com/solophp/application/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![Tests](https://github.com/solophp/application/actions/workflows/tests.yml/badge.svg)](https://github.com/solophp/application/actions/workflows/tests.yml)

Application bootstrap with PSR-15 middleware pipeline.

A lightweight, PSR-compliant application kernel that wires together your container, middleware, and HTTP layer with [solophp/router](https://github.com/solophp/router).

## Features

- PSR-15 middleware pipeline with lazy resolution from the container
- Built-in `RoutingMiddleware`: route and group middleware, route name and attributes on the request; requests it cannot route (`404`, `405` with the allowed methods, `400`) are thrown as exceptions, answered by `RoutingErrorMiddleware` or rendered by your own error middleware
- Route cache: the built router is kept in a PHP file that OPcache holds in memory, so a request skips registering and compiling routes
- `Application` is itself a PSR-15 request handler: `handle()` returns the response, `run()` emits it
- PSR-11 container integration with writable extension
- Service providers for dependency registration
- Handlers: controller methods, invokable classes, any callable
- Built on PSR interfaces, `solophp/contracts` and `solophp/router`

## Installation

```bash
composer require solophp/application
```

## Requirements

- PHP 8.3+
- A PSR-7/PSR-17 implementation (e.g., [nyholm/psr7](https://github.com/nyholm/psr7))
- A container implementing `Solo\Contracts\Container\WritableContainerInterface`
- An HTTP emitter implementing `Solo\Contracts\Http\EmitterInterface`

## Dependencies

| Dependency | Purpose |
|------------|---------|
| psr/container | Container interface |
| psr/http-message | HTTP messages |
| psr/http-server-handler | Request handler |
| psr/http-server-middleware | Middleware |
| psr/http-factory | Response factory |
| solophp/contracts (^1.1) | `WritableContainerInterface`, `EmitterInterface` |
| solophp/router (^4.0) | Route definitions, compiled matching, route cache |

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
      ├── 2. Register core services (Container, RouteDispatcher, RoutingMiddleware, RoutingErrorMiddleware)
      ├── 3. Run providers          ← your dependencies
      └── 4. Build the router       ← $config->routesPath, or load it from $config->routesCachePath,
                                      and register it as Solo\Router\Router

$app->run($request?)  =  emit($app->handle($request))
      │
      ▼
ServerRequest (provided or resolved from container)
      │
      ▼
MiddlewarePipeline ── [mw 1] → [mw 2] → … → [RoutingMiddleware] ── no route: RoutingException
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

If `handler` is `null`, the dispatcher throws `RouteNotFoundException`.

## Configuration

`Config` is an immutable DTO:

```php
new Config(
    basePath:   '/var/www/app',
    routesPath: '/var/www/app/routes.php',
    providers:  [HttpServiceProvider::class, DatabaseServiceProvider::class],
    middleware: [ErrorHandler::class, JsonParser::class, RoutingErrorMiddleware::class, RoutingMiddleware::class],
    routesCachePath: '/var/www/app/var/cache/routes.php', // optional, see Route cache
);
```

`Config` is registered in the container, so your services can read `basePath`; the kernel itself does not use it.

### `routes.php`

Returns a callable that receives a `Solo\Router\RouteCollector` and the container. `Application` builds the router from the collector once the callable returns:

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

The file is loaded in its own scope, so `$this` is not available: reach services or `Config` through the container instead. `routesPath` is found the way `require` finds files, so a relative path is looked up in the include path. A missing file throws `RuntimeException`, a file that does not return a callable throws `UnexpectedValueException`. Invalid routes, such as a duplicate name, throw when the router is built, during the constructor.

Keep the file to route definitions: with the route cache it runs only when the cache file is written, so services it registered would be missing on later boots.

### Route cache

Without a cache, every boot registers the routes and compiles their patterns into the router — under PHP-FPM, on every request. Set `routesCachePath` to keep the built router in a PHP file instead:

```php
new Config(
    /* … */,
    routesCachePath: $basePath . '/var/cache/routes.php',
);
```

The first boot runs `routes.php`, builds the router and writes the file, creating its directory if needed. Later boots load the router from the file — from OPcache when it is enabled — without running `routes.php`.

- The file is not compared with `routes.php`: delete it whenever the routes change, for example in your deploy script, and leave `routesCachePath` unset during development.
- Handlers, middleware and attributes have to be plain values: strings, numbers, booleans, `null`, arrays and enums, such as `[UserController::class, 'show']` or `AuthMiddleware::class`. A closure or an object makes the constructor throw `LogicException` naming the route, and no file is written.
- Keep the file outside the web root, in a directory only the application can write: it is executed as PHP.
- Use an absolute path: a relative one is resolved against the working directory, which differs between PHP-FPM and the CLI, so each would keep a file of its own.

See the [solophp/router cache guide](https://solophp.github.io/Router/features/caching) for details.

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

Order matters — middleware execute top-to-bottom on the way in, bottom-to-top on the way out. An exception skips the way out of every middleware it passes, so middleware that add headers to responses, such as CORS, go before every middleware that turns exceptions into responses:

```php
<?php

use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

return [
    CorsMiddleware::class,
    ErrorHandlerMiddleware::class,
    JsonParserMiddleware::class,
    RoutingErrorMiddleware::class, // answers routing errors, see Routing errors
    RoutingMiddleware::class,      // last: matches the route and runs its middleware
];
```

## Required Services

Your providers must register these into the container:

| Service ID | Interface |
|-----------|-----------|
| `Psr\Http\Message\ResponseFactoryInterface` | PSR-17 response factory |
| `Psr\Http\Message\ServerRequestInterface` | The incoming request (used when `run()` is called without an argument) |
| `Solo\Contracts\Http\EmitterInterface` | Sends the response back to the client (used by `run()`) |

`Application` builds the router itself and keeps it as `$app->router` (`Solo\Router\Router`). It hands that instance to `RoutingMiddleware` directly and registers it in the container as `Solo\Router\Router` once the providers have run, for services that need it, such as a URL generator using `getRouteByName()`. Resolve it lazily, in a factory, not inside `register()`.

`ContainerInterface`, `WritableContainerInterface`, `Config`, `Router`, `RouteDispatcher`, `RoutingMiddleware` and `RoutingErrorMiddleware` are registered by `Application` itself.

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
use Solo\HttpEmitter\Emitter;

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
2. if no route matches, throws `MethodNotAllowedException` when the path exists for other methods, and `RouteNotFoundException` otherwise;
3. decodes the route parameters, throwing `InvalidRouteParameterException` when one decodes to a control character such as `%00` or `%0A`, and writes `handler`, `params`, `route_name` and `route_attributes` to the request;
4. runs the route's middleware — group middleware from the outermost group in, then the route's own — and passes the request on to the rest of the pipeline and the dispatcher.

Route middleware use the same three forms as global middleware; `solophp/router` accepts class-strings and callables, and the route cache only class-strings and other container IDs.

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
return [ErrorHandlerMiddleware::class, RoutingErrorMiddleware::class, RoutingMiddleware::class, PermissionMiddleware::class];
```

`solophp/router` answers `HEAD` requests with `GET` routes; PHP itself does not send a response body for `HEAD`.

You can still write your own routing middleware: set `RequestAttribute::HANDLER` and the decoded `RequestAttribute::PARAMS` for the dispatcher, and run route middleware with `(new MiddlewarePipeline($container, $handler))->addFromArray($middlewares)->handle($request)`. For a request it cannot route, throw one of the exceptions below; one passed on without a handler makes the dispatcher throw `RouteNotFoundException`.

### Routing errors

`RouteNotFoundException` (`404`), `MethodNotAllowedException` (`405`; `getAllowedMethods()` lists the methods) and `InvalidRouteParameterException` (`400`) extend `Solo\Application\Exception\RoutingException`. It carries the status (`getStatusCode()`, also the exception code) and the headers the response must carry (`getHeaders()`, such as `Allow`), and `applyTo($response)` puts both on a response.

List `RoutingErrorMiddleware` right before `RoutingMiddleware` to answer them with an empty response of that status and those headers. The middleware in front of it see that response like any other.

To answer them in your own format, catch them in your error middleware instead and leave `RoutingErrorMiddleware` out:

```php
use Solo\Application\Exception\RoutingException;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ResponseFactoryInterface $responses)
    {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (RoutingException $e) {
            $response = $this->responses->createResponse()->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode(['error' => $e->getStatusCode()], JSON_THROW_ON_ERROR));

            return $e->applyTo($response);
        }
    }
}
```

`Application::handle()` answers a `RoutingException` that nothing caught with the `RoutingErrorMiddleware` from the container, outside every middleware, so none of them see that response.

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
        $response->getBody()->write(json_encode($user, JSON_THROW_ON_ERROR));
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
| `RoutingMiddleware` | Matches the route, runs route middleware, throws a `RoutingException` when it cannot |
| `RoutingErrorMiddleware` | Answers a `RoutingException` with an empty response of its status and headers |
| `Exception\RoutingException` | Base of `RouteNotFoundException`, `MethodNotAllowedException` and `InvalidRouteParameterException`; `applyTo()` puts its status and headers on a response |
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

## Upgrading from 3.x

4.0 requires PHP 8.3 or later, builds the router with `solophp/router` 4 instead of taking it from the container, and throws routing errors instead of answering them with empty responses. Projects on PHP 8.2 can stay on 3.x.

### Router

- `solophp/router` `^4.0` is now a dependency: raise your own `solophp/router` requirement to `^4.0` or drop it. The router contracts of `solophp/contracts` are no longer used. `Application` creates the `RouteCollector`, builds the router and registers it as `Solo\Router\Router`: remove the `RouterInterface` and `RouteCollector` bindings from your providers.
- A service that took `RouteCollector` or `RouterInterface` from the container, for example to generate URLs, has to take `Solo\Router\Router` and use `getRouteByName()`; a `RouteCollector` binding left in a provider would now give it an empty collector.
- `routes.php` receives a `Solo\Router\RouteCollector`: change callables type-hinted with `RouterInterface`. Route definitions follow `solophp/router` 4; see its [migration guide](https://solophp.github.io/Router/guide/migration-v4).
- `$app->router` is a `Solo\Router\Router` with `resolve()`, `getRouteByName()`, `getRoutes()` and `getAllowedMethods()`. Invalid routes, such as a duplicate name, throw in the constructor of `Application`.
- `RoutingMiddleware`'s constructor is `(Router $router, ContainerInterface $container)`: it takes the built router and no longer takes a `ResponseFactoryInterface`. To route with another router, list your own routing middleware instead; see [Routing](#routing).
- New: `Config::$routesCachePath`, see [Route cache](#route-cache).

### Routing exceptions

- `RoutingMiddleware` throws `RouteNotFoundException`, `MethodNotAllowedException` or `InvalidRouteParameterException` instead of returning an empty `404`, `405` or `400`, and `RouteDispatcher` throws `RouteNotFoundException` instead of returning `404` for a request without a handler. List `RoutingErrorMiddleware` right before `RoutingMiddleware` to keep the empty responses, or catch `RoutingException` in your error middleware to render them; see [Routing errors](#routing-errors).
- With neither in the list, `Application::handle()` answers them outside every middleware: middleware that add headers or log on the way out, such as CORS, security headers or an access log, no longer see `404`, `405` and `400` responses.
- An error middleware that catches every `Throwable` now sees these exceptions as well, unless `RoutingErrorMiddleware` is listed after it. Handle `RoutingException` first, or a `404` becomes a `500`; one that takes the status from `getCode()` gets the status right but has to add `getHeaders()`, or a `405` goes out without `Allow`.
- A middleware that customized `404` or `405` by checking the status of the response it got back no longer sees them: catch `RoutingException` instead.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
