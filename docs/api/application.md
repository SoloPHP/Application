# Application

`Solo\Application\Application` bootstraps the application in its constructor and handles requests as a PSR-15 request handler. See [Lifecycle](/guide/lifecycle) for the full sequence.

```php
final readonly class Application implements Psr\Http\Server\RequestHandlerInterface
```

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$config` | `Config` | The configuration passed to the constructor |
| `$container` | `Solo\Contracts\Container\WritableContainerInterface` | The container passed to the constructor, or the one it created |
| `$router` | `Solo\Router\Router` | The built router, also registered in the container as `Solo\Router\Router` |

All three are public and readonly.

## __construct()

```php
public function __construct(
    Config $config,
    ?WritableContainerInterface $container = null
)
```

1. Uses `$container`, or creates a `Solo\Container\Container`.
2. Registers `Config`, `ContainerInterface`, `WritableContainerInterface`, `RouteDispatcher`, `RoutingMiddleware` and `RoutingErrorMiddleware` in the container.
3. Instantiates each class in `$config->providers` without arguments and calls its `register()` with the container.
4. Builds the router: calls the callable returned by `$config->routesPath` with a new `Solo\Router\RouteCollector` and the container, then builds the collector. With `$config->routesCachePath` set, loads the router from that file instead, and builds and writes it only when the file is missing or was written by an incompatible version of Solo Router.
5. Registers the router as `Solo\Router\Router`.

Throws:

- `RuntimeException` if no container is passed and `solophp/container` is not installed;
- `InvalidArgumentException` if a provider has no public `register()` method;
- `RuntimeException` if the routes file is not found, or the route cache file cannot be written;
- `UnexpectedValueException` if the routes file does not return a callable;
- `InvalidArgumentException` from Solo Router for invalid routes, such as a duplicate name or path;
- `LogicException` if the route cache is written and a route holds a closure or another object.

## handle()

```php
public function handle(ServerRequestInterface $request): ResponseInterface
```

Runs the request through `$config->middleware` to the `RouteDispatcher` and returns the response. It does not use the emitter.

A [`MiddlewarePipeline`](/api/middleware-pipeline) is created for every call. The `RoutingErrorMiddleware` from the container wraps it, so a [`RoutingException`](/api/exceptions) that no middleware caught becomes a response of its status and headers; see [the fallback](/features/routing-errors#the-fallback). Every other exception from a middleware or a handler propagates.

Requires `Psr\Http\Message\ResponseFactoryInterface` in the container.

```php
$response = $app->handle($request);
```

## run()

```php
public function run(?ServerRequestInterface $request = null): void
```

Handles the request and emits the response:

1. Without `$request`, resolves `Psr\Http\Message\ServerRequestInterface` from the container.
2. Calls `handle()`.
3. Resolves `Solo\Contracts\Http\EmitterInterface` from the container and calls `emit()` with the response.

The emitter is resolved after handling, so the request can still replace it. In a long-running process, pass every request explicitly: see [Workers and Emitting](/features/workers).

```php
$app->run();
```
