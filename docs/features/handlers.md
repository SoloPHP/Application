# Route Handlers

`RouteDispatcher` calls the handler of the matched route. Every handler receives three arguments and returns a response:

```php
function (ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
```

- `$request` carries the [route attributes](/guide/lifecycle#request-attributes) written by `RoutingMiddleware` and by any middleware before it.
- `$response` is a new response from your `ResponseFactoryInterface`: `200` with an empty body. Write to it, or return another response.
- `$params` holds the route parameters, percent-decoded, as strings. An optional parameter missing from the path is not in the array.

A handler that returns anything but a `ResponseInterface` throws `UnexpectedValueException`, and one that cannot be called throws `InvalidArgumentException`.

## Controller method

The classic `[Class::class, 'method']` form. The class is resolved from the container, so its constructor dependencies are injected by your DI. A static method is called without an instance:

```php
<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $user = $this->users->find((int) $params['id']);
        $response->getBody()->write(json_encode($user, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

```php
$r->get('/users/{id:[0-9]+}', [UserController::class, 'show']);
```

## Invokable class

A class name, or any other container ID. The container resolves it and the dispatcher calls `__invoke()`:

```php
<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthCheck
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $response->getBody()->write('ok');

        return $response;
    }
}
```

```php
$r->get('/health', HealthCheck::class);
```

## Callable

Closures, `[$object, 'method']`, function names and `'Class::staticMethod'` strings are called as they are, without the container — useful for trivial routes and tests:

```php
$r->get('/ping', function ($request, $response, $params) {
    $response->getBody()->write('pong');

    return $response;
});
```

The container comes first, though: a string is called directly only when the container does not have it, so a service registered as `log` wins over PHP's `log()` function.

Closures and objects cannot be stored in the [route cache](/features/route-cache): use class names, `[Class::class, 'method']` arrays or function names for routes you want to cache.

## Resolution rules

| Handler | Called as |
|---------|-----------|
| `[UserController::class, 'show']`, instance method | `show()` on the instance from the container |
| `[UserController::class, 'ping']`, static method | `UserController::ping()`, without the container |
| `HealthCheck::class` or another ID the container has | `__invoke()` on the service from the container |
| A string the container does not have, such as `UserController::class . '::ping'` or a function name | The string, as a callable |
| A closure, an invokable object or `[$object, 'method']` | As it is |

Global and route middleware follow the same rule for strings.

## Reading the request

Route data and everything middleware added are request attributes:

```php
<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Application\RequestAttribute;

final class ReportController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $routeName = $request->getAttribute(RequestAttribute::ROUTE_NAME);           // 'reports.index'
        $plan = $request->getAttribute(RequestAttribute::ROUTE_ATTRIBUTES)['plan'] ?? 'free';
        $page = (int) ($request->getQueryParams()['page'] ?? 1);

        $response->getBody()->write("$routeName: $plan plan, page $page");

        return $response;
    }
}
```
