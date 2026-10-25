# Routing

Routes are defined in [`routes.php`](/guide/configuration#routes-php) with the [Solo Router](https://solophp.github.io/Router/) API. `Application` builds the router from them, and `RoutingMiddleware` uses it for every request. Add it to the middleware list, after the middleware that should run for every request:

```php
<?php

use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

return [
    ErrorHandlerMiddleware::class,
    RoutingErrorMiddleware::class,
    RoutingMiddleware::class,
];
```

For each request, `RoutingMiddleware`:

1. matches the method and the path as the client sent it, with only percent-encoded non-ASCII bytes decoded;
2. if no route matches, throws `MethodNotAllowedException` when the path exists for other methods, and `RouteNotFoundException` otherwise;
3. decodes the route parameters, throwing `InvalidRouteParameterException` when one decodes to a control character such as `%00` or `%0A`, and writes `handler`, `params`, `route_name` and `route_attributes` to the request;
4. runs the route's middleware — group middleware from the outermost group in, then the route's own — and passes the request on to the rest of the pipeline and the dispatcher.

The exceptions are described in [Routing Errors](/features/routing-errors).

## Defining routes

```php
<?php

use App\Controller\InvoiceController;
use App\Controller\UserController;
use App\Middleware\AuthMiddleware;
use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    $r->get('/users/{id:[0-9]+}', [UserController::class, 'show'])->name('users.show');

    $r->group('/billing', function (RouteCollector $r): void {
        $r->get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        $r->post('/invoices', [InvoiceController::class, 'store'])
            ->name('invoices.store')
            ->attribute('permission', 'billing.manage');
    }, [AuthMiddleware::class], ['permission' => 'billing.view']);
};
```

Parameters, optional segments, groups, names and attributes work as described in the Solo Router documentation: [parameters](https://solophp.github.io/Router/features/parameters), [optional segments](https://solophp.github.io/Router/features/optional-segments), [groups](https://solophp.github.io/Router/features/groups), [named routes](https://solophp.github.io/Router/features/named-routes) and [attributes](https://solophp.github.io/Router/features/attributes). Invalid routes, such as a duplicate name, make the `Application` constructor throw `InvalidArgumentException`.

## Paths and parameters

The router matches the path as the client sent it, with only percent-encoded non-ASCII bytes decoded. Escapes of ASCII characters stay, so `%2F` never splits a segment:

| Route | Request path | Result |
|-------|--------------|--------|
| `/café` | `/caf%C3%A9` | Matches |
| `/admin` | `/%61dmin` | `404`: `%61` is not decoded |
| `/files/{name}` | `/files/a%2Fb` | Matches, `name` is `a/b` |
| `/files/{name}` | `/files/a%00b` | `400`: the parameter decodes to a control character |
| `/posts[/{page}]` | `/posts` | Matches, `params` has no `page` key |
| `/` | empty path | Matches |

- Parameters reach the handler percent-decoded, as strings. An optional parameter that is not in the path is left out of `params`, so read it with a default: `$params['page'] ?? '1'`.
- The query string is not part of the match. Read it with `$request->getQueryParams()`.
- Methods are matched case-sensitively, as the router does: a `get` request gets `405` from a `GET` route.

Middleware before `RoutingMiddleware` see the same path structure as the router, so a check on `$request->getUri()->getPath()` cannot be bypassed with escapes such as `/%61dmin`. Parameters, though, are decoded: `/files/a%2Fb` gives `name` = `a/b` for `/files/{name}`, so validate parameters before building file paths from them.

## HEAD and OPTIONS

Solo Router answers `HEAD` requests with `GET` routes, unless a `HEAD` route matches the path. PHP itself does not send a response body for `HEAD`.

`OPTIONS` requests get no special treatment: a path with only `GET` routes answers `405` to them. Answer CORS preflight requests in a middleware listed before `RoutingMiddleware`, or register `options()` routes.

## Route middleware

Route and group middleware are resolved like [global middleware](/features/middleware): a class name or other container ID is taken from the container when the request reaches it, and a callable is called as it is. Solo Router accepts class names, other container IDs and callables in `middleware()`; the [route cache](/features/route-cache) accepts only class names and other container IDs.

They run in this order, after the global middleware listed before `RoutingMiddleware`:

```php
<?php

use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    $r->group('/api', function (RouteCollector $r): void {
        $r->group('/admin', function (RouteCollector $r): void {
            $r->get('/stats', [StatsController::class, 'index'])
                ->middleware(CacheMiddleware::class);
        }, [AdminMiddleware::class]);
    }, [ApiMiddleware::class]);
};

// ApiMiddleware → AdminMiddleware → CacheMiddleware
//   → global middleware listed after RoutingMiddleware → StatsController::index
```

A route middleware can stop the request by returning its own response; the handler is then not called.

## Reading the route

Middleware listed after `RoutingMiddleware` run after the route middleware and can read the route from the request. For example, a permission check driven by route attributes:

```php
<?php

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
```

```php
<?php
// app/middleware.php

use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

return [
    ErrorHandlerMiddleware::class,
    RoutingErrorMiddleware::class,
    RoutingMiddleware::class,
    PermissionMiddleware::class,
];
```

Handlers read the same attributes; see [request attributes](/guide/lifecycle#request-attributes). The router itself is `$app->router`, also registered in the container as `Solo\Router\Router`: services can use it to look up routes by name, see [services that need the router](/features/service-providers#services-that-need-the-router).

## Custom routing middleware

You can replace `RoutingMiddleware` with your own, for example to route with another router. A routing middleware has to:

- set `RequestAttribute::HANDLER` and the decoded `RequestAttribute::PARAMS` for the dispatcher;
- run route middleware, if it has any, with `(new MiddlewarePipeline($container, $handler))->addFromArray($middlewares)->handle($request)`;
- throw a [routing exception](/features/routing-errors) for a request it cannot route. A request passed on without a handler makes the dispatcher throw `RouteNotFoundException`.

A minimal one that routes exact paths from an array:

```php
<?php

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Application\Exception\MethodNotAllowedException;
use Solo\Application\Exception\RouteNotFoundException;
use Solo\Application\MiddlewarePipeline;
use Solo\Application\RequestAttribute;

final class MapRoutingMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, array<string, array{handler: mixed, middleware?: list<string>}>> $routes
     *        path => method => route
     */
    public function __construct(
        private readonly array $routes,
        private readonly ContainerInterface $container,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $methods = $this->routes[$path] ?? throw new RouteNotFoundException($method, $path);
        $route = $methods[$method] ?? throw new MethodNotAllowedException($method, $path, array_keys($methods));

        $request = $request
            ->withAttribute(RequestAttribute::HANDLER, $route['handler'])
            ->withAttribute(RequestAttribute::PARAMS, []);

        return (new MiddlewarePipeline($this->container, $handler))
            ->addFromArray($route['middleware'] ?? [])
            ->handle($request);
    }
}
```

Register it in a provider and list `MapRoutingMiddleware::class` in place of `RoutingMiddleware::class`:

```php
$container->set(MapRoutingMiddleware::class, fn(ContainerInterface $c) => new MapRoutingMiddleware([
    '/health' => ['GET' => ['handler' => HealthCheck::class]],
    '/reports' => ['GET' => ['handler' => [ReportController::class, 'index'], 'middleware' => [AuthMiddleware::class]]],
], $c));
```
