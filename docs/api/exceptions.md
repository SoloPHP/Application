# Exceptions

## Routing exceptions

Requests that reach no route handler are reported with exceptions in `Solo\Application\Exception`. See [Routing Errors](/features/routing-errors) for how to answer them.

```
RuntimeException
└── RoutingException (abstract)
    ├── RouteNotFoundException          404
    ├── MethodNotAllowedException       405, Allow
    └── InvalidRouteParameterException  400
```

### RoutingException

```php
abstract class RoutingException extends RuntimeException
```

The base class. The HTTP status is also the exception code.

```php
public function __construct(
    string $message,
    int $statusCode,
    array $headers = [],
    ?Throwable $previous = null,
)
```

`$headers` maps header names to lists of values, `array<string, list<string>>`.

| Method | Returns |
|--------|---------|
| `getStatusCode(): int` | The HTTP status, the same as `getCode()` |
| `getHeaders(): array` | Headers the response must carry, `array<string, list<string>>`, such as `['Allow' => ['GET', 'HEAD']]` |
| `applyTo(ResponseInterface $response): ResponseInterface` | The response with this status and these headers; its body and other headers stay |

```php
use Solo\Application\Exception\RoutingException;

try {
    return $handler->handle($request);
} catch (RoutingException $e) {
    return $e->applyTo($responseFactory->createResponse());
}
```

Extend it for routing failures of your own; see [your own routing exceptions](/features/routing-errors#your-own-routing-exceptions).

### RouteNotFoundException

```php
final class RouteNotFoundException extends RoutingException
public function __construct(string $method, string $path)
```

No route matches the request: status `404`, no headers. Thrown by `RoutingMiddleware`, and by `RouteDispatcher` for a request without a handler. Message: `No route matches GET /missing`.

### MethodNotAllowedException

```php
final class MethodNotAllowedException extends RoutingException
public function __construct(string $method, string $path, array $allowedMethods)
public function getAllowedMethods(): array
```

The path is routed for other methods only: status `405` and an `Allow` header with `$allowedMethods`. `getAllowedMethods()` returns them as `list<string>`. An empty list gives an empty `Allow` header, which tells the client that the resource allows no method at the moment. Message: `Method DELETE is not allowed for /users/1; allowed: GET, HEAD`.

### InvalidRouteParameterException

```php
final class InvalidRouteParameterException extends RoutingException
public function __construct(string $method, string $path, string $parameter)
```

A route parameter decodes to a control character, such as NUL or a line break: status `400`, no headers. Message: `Route parameter "name" in GET /files/a%00b decodes to a control character`.

Messages escape control characters in the method, so a request cannot break a log line.

## Other exceptions

Configuration errors and invalid handlers or middleware are reported with SPL exceptions:

| Exception | Thrown by | When |
|-----------|-----------|------|
| `RuntimeException` | `Application` constructor | No container passed and `solophp/container` not installed |
| `InvalidArgumentException` | `Application` constructor | A provider has no public `register()` method |
| `RuntimeException` | `Application` constructor | The routes file is not found |
| `UnexpectedValueException` | `Application` constructor | The routes file does not return a callable |
| `InvalidArgumentException` | `Application` constructor, from Solo Router | Invalid routes, such as a duplicate name or path |
| `LogicException` | `Application` constructor, from Solo Router | The route cache is written and a route holds a closure or another object |
| `RuntimeException` | `Application` constructor, from Solo Router | The route cache file cannot be written |
| `InvalidArgumentException` | `MiddlewarePipeline` | A middleware resolves to neither a `MiddlewareInterface` nor a callable |
| `UnexpectedValueException` | `MiddlewarePipeline` | A callable middleware returns anything but a response |
| `InvalidArgumentException` | `RouteDispatcher` | The route handler cannot be called |
| `UnexpectedValueException` | `RouteDispatcher` | The route handler returns anything but a response |

A service or class name the container cannot resolve throws the container's `Psr\Container\NotFoundExceptionInterface`.
