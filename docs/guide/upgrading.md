# Upgrading from 3.x

Version 4.0.1 requires PHP 8.3 or later, builds the router with [Solo Router 4](https://solophp.github.io/Router/) instead of taking it from the container, and throws routing errors instead of answering them with empty responses. Projects on PHP 8.2 can stay on 3.x. Coming from 4.0.0, an earlier state of this release, see [from 4.0.0](#from-4-0-0).

## Dependencies

`solophp/router` `^4.0` is now a dependency of `solophp/application`. Raise your own `solophp/router` requirement together with the application, or drop it if you do not use the router directly:

```bash
composer require "solophp/application:^4.0.1" "solophp/router:^4.0"
```

The router contracts of `solophp/contracts`, `RouterInterface` and `AllowedMethodsInterface`, are no longer used. The container and the emitter still come through `WritableContainerInterface` and `EmitterInterface`.

## Router

### Remove the router bindings

In 3.x a provider registered the router, and `Application` took it from the container:

```php
// 3.x
$container->set(RouterInterface::class, fn() => new RouteCollector());
$container->set(RouteCollector::class, fn(ContainerInterface $c) => $c->get(RouterInterface::class));
```

Delete both bindings. `Application` now creates the `RouteCollector` itself, builds the router and registers it as `Solo\Router\Router` once the providers have run. A `RouteCollector` binding left in a provider would give the services that take it an empty collector.

### Update routes.php

`routes.php` receives a `Solo\Router\RouteCollector`: change callables type-hinted with `RouterInterface`.

```php
<?php

use Psr\Container\ContainerInterface;
use Solo\Router\RouteCollector;

return function (RouteCollector $r, ContainerInterface $container): void {
    $r->get('/users/{id}', [UserController::class, 'show'])->name('users.show');
};
```

Route definitions follow Solo Router 4: for example, duplicate names and paths now throw when the router is built, which happens in the `Application` constructor. See the [router migration guide](https://solophp.github.io/Router/guide/migration-v4).

### Services that use the router

A service that took `RouteCollector` or `RouterInterface` from the container, for example to generate URLs, has to take `Solo\Router\Router` and use `getRouteByName()`:

```php
// 3.x
public function __construct(private RouteCollector $routes) {}

// 4.0.1
public function __construct(private Router $router) {}
```

Resolve it lazily, in a factory, not inside a provider's `register()`. See [services that need the router](/features/service-providers#services-that-need-the-router).

### `$app->router`

`$app->router` is a `Solo\Router\Router` with `resolve()`, `getRouteByName()`, `getRoutes()` and `getAllowedMethods()`. See the [Router API](https://solophp.github.io/Router/api/router).

### `RoutingMiddleware`

The constructor is `(Router $router, ContainerInterface $container)`: it takes the built router and no longer takes a `ResponseFactoryInterface`. If you created it yourself, take it from the container instead, where `Application` registers it. To route with another router, list your own routing middleware: see [custom routing middleware](/features/routing#custom-routing-middleware).

### New: route cache

`Config::$routesCachePath` keeps the built router in a PHP file, so a request skips registering and compiling routes. See [Route Cache](/features/route-cache).

## Routing exceptions

`RoutingMiddleware` throws `RouteNotFoundException`, `MethodNotAllowedException` or `InvalidRouteParameterException` instead of returning an empty `404`, `405` or `400`. `RouteDispatcher` throws `RouteNotFoundException` instead of returning `404` for a request without a handler. All three extend `Solo\Application\Exception\RoutingException`.

### Keep the empty responses

List `RoutingErrorMiddleware` right before `RoutingMiddleware`. The middleware in front of it see its responses as they saw the responses of `RoutingMiddleware` in 3.x:

```php
<?php

use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

return [
    CorsMiddleware::class,
    ErrorHandlerMiddleware::class,
    RoutingErrorMiddleware::class, // new in 4.0
    RoutingMiddleware::class,
];
```

Or catch `RoutingException` in your error middleware to render the errors in your own format: see [routing errors](/features/routing-errors#rendering-errors-yourself).

### What changes if you do neither

- `Application::handle()` answers the exceptions outside every middleware. Middleware that add headers or log on the way out, such as CORS, security headers or an access log, no longer see `404`, `405` and `400` responses.
- An error middleware that catches every `Throwable` now sees these exceptions as well, unless `RoutingErrorMiddleware` is listed after it. Handle `RoutingException` first, or a `404` becomes a `500`. One that takes the status from `getCode()` gets the status right but has to add `getHeaders()`, or a `405` goes out without `Allow`.
- A middleware that customized `404` or `405` by checking the status of the response it got back no longer sees them: catch `RoutingException` instead.

For example, a middleware that rendered a `404` page:

```php
// 3.x
$response = $handler->handle($request);

return $response->getStatusCode() === 404 ? $this->notFoundPage() : $response;
```

```php
// 4.0.1
try {
    return $handler->handle($request);
} catch (RouteNotFoundException) {
    return $this->notFoundPage();
}
```

A `404` that a controller returns itself is a response, not an exception, and passes through unchanged.

## From 4.0.0

4.0.0, published on 17 October 2026, is an earlier state of this release. It already throws routing errors, so the [routing exceptions](#routing-exceptions) part of this guide is done, but it takes a `solophp/router` 3.5 router from the container and supports PHP 8.2. Follow the [router](#router) part:

- move to PHP 8.3 and require `"solophp/application:^4.0.1"` together with `"solophp/router:^4.0"`;
- remove the `RouterInterface` and `RouteCollector` bindings from your providers;
- type-hint `routes.php` with `RouteCollector` and make the services that use the router take `Solo\Router\Router`.

`RoutingMiddleware` keeps its two arguments, but the first one is now a `Solo\Router\Router` instead of a `RouterInterface`.

## Checklist

- PHP 8.3 or later
- `solophp/application` `^4.0.1`; `solophp/router` `^4.0` or removed from your `composer.json`
- No `RouterInterface` or `RouteCollector` bindings in providers
- `routes.php` type-hinted with `RouteCollector` and valid for Solo Router 4
- Services that need the router take `Solo\Router\Router`
- `RoutingErrorMiddleware` listed before `RoutingMiddleware`, or `RoutingException` handled in your error middleware
- Optionally, `routesCachePath` for production
