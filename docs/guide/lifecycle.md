# Lifecycle

`Application` separates *bootstrap*, done once in the constructor, from *handling*, done for every request:

```
new Application($config, $container = null)
      │
      ├── 1. Create / accept container
      ├── 2. Register core services (Config, container, RouteDispatcher,
      │                              RoutingMiddleware, RoutingErrorMiddleware)
      ├── 3. Run providers          ← your services
      └── 4. Build the router       ← routes.php, or load it from routesCachePath,
                                      and register it as Solo\Router\Router

$app->run($request?)  =  emit($app->handle($request))
      │
      ▼
ServerRequest (provided or resolved from the container)
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
                              global middleware listed after RoutingMiddleware
                                                     │
                                                     ▼
RouteDispatcher  →  Controller / invokable / callable
      │
      ▼
ResponseInterface  →  returned by handle(), emitted by run()
```

## Bootstrap

The constructor runs four steps in order:

1. **Container.** The container you pass, or a new `Solo\Container\Container`. Without either, the constructor throws `RuntimeException`.
2. **Core services.** `Application` registers factories for `Config`, `Psr\Container\ContainerInterface`, `Solo\Contracts\Container\WritableContainerInterface`, `RouteDispatcher`, `RoutingMiddleware` and `RoutingErrorMiddleware`. Nothing is created yet.
3. **Providers.** Each class in `Config::$providers` is instantiated without arguments, and its `register()` method is called with the container, in list order.
4. **Router.** `Application` creates a `Solo\Router\RouteCollector`, calls the callable returned by `routes.php` with the collector and the container, and builds the router. With [`routesCachePath`](/features/route-cache) set, it loads the router from the cache file instead and runs `routes.php` only when the file has to be written. The router is kept as `$app->router` and registered in the container as `Solo\Router\Router`.

Providers run before the routes, so `routes.php` can take services from the container. The router is registered after the providers, so a provider must not resolve it inside `register()`: take it in a factory that runs later, see [service providers](/features/service-providers#services-that-need-the-router).

Every configuration error surfaces here, in the constructor, before any request is handled:

| Problem | Exception |
|---------|-----------|
| No container passed and `solophp/container` not installed | `RuntimeException` |
| A provider without a public `register()` method | `InvalidArgumentException` |
| `routesPath` does not exist | `RuntimeException` |
| `routes.php` does not return a callable | `UnexpectedValueException` |
| Invalid routes, such as a duplicate name or path | `InvalidArgumentException` from `build()` |
| A closure or an object in a route when the route cache is written | `LogicException` |

## Handling a request

`handle()` takes a `ServerRequestInterface` and returns a `ResponseInterface`:

1. It creates a [`MiddlewarePipeline`](/api/middleware-pipeline) whose final handler is the `RouteDispatcher` from the container, and adds `Config::$middleware` to it.
2. It passes the request to the pipeline through the `RoutingErrorMiddleware` from the container, so a routing error that no middleware caught still becomes a response. See [routing errors](/features/routing-errors#the-fallback).
3. Global middleware run in list order. A class name or another container ID is resolved when the request reaches it.
4. [`RoutingMiddleware`](/features/routing) resolves the route, writes the route to the request attributes and runs the route middleware: group middleware from the outermost group in, then the route's own.
5. Global middleware listed after `RoutingMiddleware` run next and can read the route.
6. [`RouteDispatcher`](/features/handlers) resolves the handler and calls it with the request, a new response and the route parameters.
7. The response travels back out through every middleware in reverse order and is returned.

`run()` wraps `handle()` for a classic PHP-FPM front controller:

```php
public function run(?ServerRequestInterface $request = null): void
```

Called without an argument, it resolves `ServerRequestInterface` from the container. It then calls `handle()`, resolves `Solo\Contracts\Http\EmitterInterface` and emits the response. The emitter is resolved after handling, so a request can still replace it, for example to stream the response. To get the response without emitting it, call `handle()`: see [workers and emitting](/features/workers).

## Request attributes

`RoutingMiddleware` writes these request attributes; `RouteDispatcher` reads `handler` and `params`. The names are available as constants on [`RequestAttribute`](/api/request-attribute):

| Attribute | Constant | Type |
|-----------|----------|------|
| `handler` | `RequestAttribute::HANDLER` | `array{class-string,string} \| string \| callable` |
| `params` | `RequestAttribute::PARAMS` | `array<string,string>`, percent-decoded |
| `route_name` | `RequestAttribute::ROUTE_NAME` | `string \| null` |
| `route_attributes` | `RequestAttribute::ROUTE_ATTRIBUTES` | `array<string,mixed>` |

If `handler` is missing, for example because no routing middleware is listed, the dispatcher throws `RouteNotFoundException`.

## Container services

`Application` registers these services itself:

| Service ID | Value |
|------------|-------|
| `Solo\Application\Config` | The `Config` passed to the constructor |
| `Psr\Container\ContainerInterface` | The container itself |
| `Solo\Contracts\Container\WritableContainerInterface` | The container itself |
| `Solo\Application\RouteDispatcher` | Dispatcher using your `ResponseFactoryInterface` |
| `Solo\Application\RoutingMiddleware` | Routing middleware holding `$app->router` |
| `Solo\Application\RoutingErrorMiddleware` | Error middleware using your `ResponseFactoryInterface` |
| `Solo\Router\Router` | `$app->router`, registered after the providers |

Your providers register the rest:

| Service ID | Used by |
|------------|---------|
| `Psr\Http\Message\ResponseFactoryInterface` | `RouteDispatcher` and `RoutingErrorMiddleware`, on every `handle()` |
| `Psr\Http\Message\ServerRequestInterface` | `run()` called without a request |
| `Solo\Contracts\Http\EmitterInterface` | `run()` |

Middleware, controllers and handlers referenced by class name or ID must resolve from the container as well.

## Components

| Class | Description |
|-------|-------------|
| [`Application`](/api/application) | Bootstraps the application; PSR-15 handler (`handle()`) and `run()` to emit |
| [`Config`](/api/config) | Immutable configuration DTO |
| [`MiddlewarePipeline`](/api/middleware-pipeline) | Reusable PSR-15 middleware pipeline, a request handler itself |
| [`RoutingMiddleware`](/api/routing-middleware) | Matches the route, runs route middleware, throws a `RoutingException` when it cannot |
| [`RoutingErrorMiddleware`](/api/routing-error-middleware) | Answers a `RoutingException` with an empty response of its status and headers |
| [`RouteDispatcher`](/api/route-dispatcher) | Resolves and calls the matched route handler |
| [`RequestAttribute`](/api/request-attribute) | Names of the request attributes used for routing |
| [`Exception\RoutingException`](/api/exceptions) | Base of `RouteNotFoundException`, `MethodNotAllowedException` and `InvalidRouteParameterException` |
