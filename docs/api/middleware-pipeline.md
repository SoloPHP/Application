# MiddlewarePipeline

`Solo\Application\MiddlewarePipeline` runs a list of PSR-15 middleware in front of a final request handler. It is a request handler itself. See [Middleware](/features/middleware) for usage.

```php
final class MiddlewarePipeline implements Psr\Http\Server\RequestHandlerInterface
```

`Application::handle()` creates one per request with the `RouteDispatcher` as the final handler, and `RoutingMiddleware` creates one for the route middleware.

## __construct()

```php
public function __construct(
    ContainerInterface $container,
    RequestHandlerInterface $finalHandler
)
```

`$container` resolves middleware given as IDs. `$finalHandler` handles the request once every middleware has passed it on.

## add()

```php
public function add(MiddlewareInterface|callable|string $middleware): self
```

Appends a middleware and returns the pipeline:

- a `MiddlewareInterface` instance;
- a callable with the signature of `process()`: `fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`;
- a container ID, such as a class name, that resolves to either.

Nothing is resolved or checked when a middleware is added.

## addFromArray()

```php
public function addFromArray(array $middlewares): self
```

Calls `add()` for each element, in order, and returns the pipeline.

## handle()

```php
public function handle(ServerRequestInterface $request): ResponseInterface
```

Passes the request to the first middleware, with a handler for the rest of the list; once the list is exhausted, calls the final handler. Each entry is resolved when the request reaches it:

- a string is a container ID, unless the container does not have it and PHP can call it, such as `'Class::staticMethod'`;
- anything else is used as it is.

The pipeline keeps no per-request state: it can handle any number of requests, and a middleware may call its `$handler` more than once.

Throws:

- `InvalidArgumentException` if an entry resolves to neither a `MiddlewareInterface` nor a callable;
- `UnexpectedValueException` if a callable middleware returns anything but a `ResponseInterface`;
- the container's `NotFoundExceptionInterface` for an ID it cannot resolve.

```php
use Solo\Application\MiddlewarePipeline;

$response = (new MiddlewarePipeline($container, $finalHandler))
    ->add(JsonParserMiddleware::class)
    ->addFromArray([AuthMiddleware::class, new RateLimitMiddleware(60)])
    ->handle($request);
```
