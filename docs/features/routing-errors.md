# Routing Errors

`RoutingMiddleware` throws an exception for a request it cannot route:

| Exception | Status | Thrown when | Headers |
|-----------|--------|-------------|---------|
| `RouteNotFoundException` | `404` | No route matches the path | — |
| `MethodNotAllowedException` | `405` | The path exists for other methods only | `Allow` |
| `InvalidRouteParameterException` | `400` | A route parameter decodes to a control character, such as `%00` or `%0A` | — |

`RouteDispatcher` also throws `RouteNotFoundException` for a request that reaches it without a handler, for example when no routing middleware is listed.

All three are in `Solo\Application\Exception` and extend `RoutingException`, a `RuntimeException`. It carries:

- the status: `getStatusCode()`, also the exception code;
- the headers the response must carry: `getHeaders()`, such as `['Allow' => ['GET', 'HEAD']]`;
- `applyTo($response)`, which puts both on a response and keeps its body and other headers.

`MethodNotAllowedException::getAllowedMethods()` lists the allowed methods. Messages name the request, such as `No route matches GET /missing`, with control characters in the method escaped, so they are safe to write to a log line.

There are three ways to turn these exceptions into responses.

## Empty responses

List `RoutingErrorMiddleware` right before `RoutingMiddleware`. It answers a `RoutingException` with an empty response of that status and those headers:

```php
<?php

use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

return [
    CorsMiddleware::class,
    AccessLogMiddleware::class,
    ErrorHandlerMiddleware::class,
    RoutingErrorMiddleware::class,
    RoutingMiddleware::class,
];
```

The middleware in front of it see that response like any other: `CorsMiddleware` adds its headers to a `404` and `AccessLogMiddleware` logs it.

## Rendering errors yourself

To answer them in your own format, catch `RoutingException` in your error middleware and leave `RoutingErrorMiddleware` out, or it would catch them first:

```php
<?php

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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

```php
<?php

use Solo\Application\RoutingMiddleware;

return [
    CorsMiddleware::class,
    ErrorHandlerMiddleware::class,
    RoutingMiddleware::class,
];
```

`applyTo()` sets the status and adds `Allow` to a `405`, so the response stays correct whatever body you write.

### Catch-all error handlers

An error middleware that catches every `Throwable` sees routing exceptions too, unless `RoutingErrorMiddleware` is listed after it. Catch `RoutingException` first, or a `404` becomes a `500`:

```php
try {
    return $handler->handle($request);
} catch (RoutingException $e) {
    return $e->applyTo($this->responses->createResponse());
} catch (Throwable $e) {
    $this->logger->error($e->getMessage(), ['exception' => $e]);

    return $this->responses->createResponse(500);
}
```

A handler that takes the status from `getCode()` gets it right, but has to add `getHeaders()` as well, or a `405` goes out without `Allow`.

## The fallback

`Application::handle()` answers a `RoutingException` that nothing caught with the `RoutingErrorMiddleware` from the container. It wraps the whole pipeline, so none of your middleware see that response: with neither of the options above, CORS headers, security headers and access logs miss `404`, `405` and `400` responses.

Because the fallback is taken from the container, a provider can register its own middleware under the `RoutingErrorMiddleware` ID. It replaces the fallback and, if you list `RoutingErrorMiddleware::class`, the listed middleware as well:

```php
$container->set(
    RoutingErrorMiddleware::class,
    fn(ContainerInterface $c) => new JsonRoutingErrorMiddleware($c->get(ResponseFactoryInterface::class)),
);
```

The service must implement `MiddlewareInterface`.

## Your own routing exceptions

`RoutingException` is abstract, with a public constructor: `(string $message, int $statusCode, array $headers = [], ?Throwable $previous = null)`. Extend it for a request your own code cannot route, and `RoutingErrorMiddleware` or your error middleware answer it like the built-in ones:

```php
<?php

use Solo\Application\Exception\RoutingException;

final class GoneException extends RoutingException
{
    public function __construct(string $path)
    {
        parent::__construct("$path is gone", 410);
    }
}
```

`RoutingErrorMiddleware` catches it wherever it is thrown inside it: in `RoutingMiddleware`, in route middleware or in a handler.

See the [exceptions reference](/api/exceptions).
