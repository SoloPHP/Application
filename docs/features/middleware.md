# Middleware

Global middleware are listed in `Config::$middleware`, outermost first. A middleware is any of:

- a class name or other container ID, resolved *lazily* — only when the request reaches it;
- a `Psr\Http\Server\MiddlewareInterface` instance;
- a callable with the signature of `process()`: `fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`. A string the container does not have but PHP can call, such as `'Class::staticMethod'`, counts as one.

A typical middleware implements `MiddlewareInterface`:

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
<?php

use App\Middleware\JsonParserMiddleware;
use Solo\Application\Config;
use Solo\Application\RoutingMiddleware;

$config = new Config(
    basePath: $basePath,
    routesPath: $basePath . '/app/routes.php',
    providers: require $basePath . '/app/providers.php',
    middleware: [
        new SomeMiddleware($dependency),
        fn($request, $handler) => $handler->handle($request)->withHeader('X-Powered-By', 'Solo'),
        JsonParserMiddleware::class,
        RoutingMiddleware::class,
    ],
);
```

Class names keep the list cheap: a middleware is created only when a request reaches it, so one after a middleware that answers early is never created. `solophp/container` creates classes it has no factory for by autowiring their constructors; with another container, register them.

## Order

Middleware execute top to bottom on the way in and bottom to top on the way out:

```
request  → CorsMiddleware → ErrorHandlerMiddleware → RoutingMiddleware → handler
response ← CorsMiddleware ← ErrorHandlerMiddleware ← RoutingMiddleware ←
```

An exception skips the way out of every middleware it passes until one catches it. So middleware that add headers to responses, such as CORS, go before every middleware that turns exceptions into responses, or the error responses miss their headers.

`RoutingMiddleware` routes the request, so it splits the list:

- middleware listed before it run for every request, including requests that match no route;
- middleware listed after it run only for matched routes, after the route middleware, and can [read the route](/features/routing#reading-the-route).

## Stateless and reusable

`MiddlewarePipeline` keeps no per-request state: the same pipeline can handle any number of requests, and a middleware may call `$handler->handle()` more than once, for example to retry:

```php
<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RetryOnDeadlockMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (DeadlockException) {
            return $handler->handle($request);
        }
    }
}
```

The middleware themselves are shared when the container shares instances, as `solophp/container` does: in a [worker runtime](/features/workers) one instance handles every request. Keep request data out of their properties.

## Route middleware

Routes and groups have middleware of their own, which `RoutingMiddleware` runs after matching: group middleware from the outermost group in, then the route's own. They are resolved the same way as global middleware. See [route middleware](/features/routing#route-middleware).

## Using the pipeline directly

`MiddlewarePipeline` is a PSR-15 request handler of its own. Give it a container and a final handler, add middleware and handle requests:

```php
<?php

use Solo\Application\MiddlewarePipeline;

$pipeline = (new MiddlewarePipeline($container, $finalHandler))
    ->add(JsonParserMiddleware::class)
    ->add(fn($request, $handler) => $handler->handle($request)->withHeader('X-Powered-By', 'Solo'));

$response = $pipeline->handle($request);
```

`Application::handle()` builds one this way for every request, with the `RouteDispatcher` as the final handler, and so does `RoutingMiddleware` for route middleware. See the [MiddlewarePipeline API](/api/middleware-pipeline).

## Errors

| Problem | Exception |
|---------|-----------|
| A container ID that does not resolve | The container's `Psr\Container\NotFoundExceptionInterface` |
| An entry that resolves to neither a `MiddlewareInterface` nor a callable | `InvalidArgumentException` |
| A callable middleware that returns anything but a `ResponseInterface` | `UnexpectedValueException` |

They are thrown when a request reaches the middleware, not when it is added.
