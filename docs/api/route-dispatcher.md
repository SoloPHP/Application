# RouteDispatcher

`Solo\Application\RouteDispatcher` resolves and calls the handler stored on the request. It is the final handler of the pipeline that `Application::handle()` builds. See [Route Handlers](/features/handlers) for usage.

```php
final readonly class RouteDispatcher implements Psr\Http\Server\RequestHandlerInterface
```

`Application` registers it in the container.

## __construct()

```php
public function __construct(
    ContainerInterface $container,
    ResponseFactoryInterface $responseFactory
)
```

`$container` resolves handlers given as IDs or `[class, method]` arrays. `$responseFactory` creates the response passed to the handler.

## handle()

```php
public function handle(ServerRequestInterface $request): ResponseInterface
```

1. Reads the `RequestAttribute::HANDLER` attribute and throws `RouteNotFoundException` if it is missing.
2. Resolves the handler:
   - a string is taken from the container, unless the container does not have it and PHP can call it, such as a function name or `'Class::staticMethod'`;
   - a `[class-string, method]` array calls the method on the instance from the container, unless the method is static;
   - anything else is used as it is.
3. Calls it with the request, `$responseFactory->createResponse()` and the `RequestAttribute::PARAMS` attribute, `[]` if missing.
4. Returns the response.

Throws:

- `RouteNotFoundException` if the request has no handler;
- `InvalidArgumentException` if the handler cannot be called, for example a method that does not exist;
- `UnexpectedValueException` if the handler returns anything but a `ResponseInterface`;
- whatever the container or the handler throws.
