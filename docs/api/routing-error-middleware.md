# RoutingErrorMiddleware

`Solo\Application\RoutingErrorMiddleware` answers a [`RoutingException`](/api/exceptions) with an empty response of its status and headers. See [Routing Errors](/features/routing-errors) for usage.

```php
final readonly class RoutingErrorMiddleware implements Psr\Http\Server\MiddlewareInterface
```

List it right before `RoutingMiddleware`, so every middleware in front of it sees and can decorate its responses. `Application` registers it in the container, and `Application::handle()` also wraps the whole pipeline in the instance from the container, to answer routing exceptions that nothing caught.

## __construct()

```php
public function __construct(ResponseFactoryInterface $responseFactory)
```

`$responseFactory` creates the responses.

## process()

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
```

Returns the response of `$handler`. If `$handler` throws a `RoutingException`, returns `$exception->applyTo($responseFactory->createResponse())`: an empty response with the exception's status and headers, such as `Allow` for `405`. Other exceptions propagate.
