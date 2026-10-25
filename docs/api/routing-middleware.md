# RoutingMiddleware

`Solo\Application\RoutingMiddleware` matches the request against the router, stores the route in request attributes and runs the route's own middleware before passing the request on. See [Routing](/features/routing) for usage.

```php
final readonly class RoutingMiddleware implements Psr\Http\Server\MiddlewareInterface
```

`Application` registers it in the container with `$app->router`, so list it as `RoutingMiddleware::class`.

## __construct()

```php
public function __construct(
    Solo\Router\Router $router,
    ContainerInterface $container,
)
```

`$router` is the built router to match against. `$container` resolves route middleware given as IDs.

## process()

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
```

1. Resolves the request method and path with `Router::resolve()`. The path is the one the client sent, with a leading slash and with only percent-encoded non-ASCII bytes decoded.
2. Throws `RouteNotFoundException` if no route matches, or `MethodNotAllowedException` with the allowed methods if the path exists for other methods.
3. Percent-decodes the parameters and throws `InvalidRouteParameterException` if one contains a control character (`\x00`–`\x1F`, `\x7F`).
4. Adds the request attributes:

   | Attribute | Value |
   |-----------|-------|
   | `RequestAttribute::HANDLER` | `$route->handler` |
   | `RequestAttribute::PARAMS` | The decoded parameters, `array<string, string>` |
   | `RequestAttribute::ROUTE_NAME` | `$route->name`, or `null` |
   | `RequestAttribute::ROUTE_ATTRIBUTES` | `$route->attributes` |

5. Runs `$route->middlewares` — group middleware from the outermost group in, then the route's own — in a [`MiddlewarePipeline`](/api/middleware-pipeline) whose final handler is `$handler`, and returns its response.

Throws the [routing exceptions](/api/exceptions) above, and whatever the route middleware or the rest of the pipeline throw.
