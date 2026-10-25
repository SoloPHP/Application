# Config

`Solo\Application\Config` is an immutable DTO with the application's configuration. See [Configuration](/guide/configuration) for the files it points to.

```php
final readonly class Config
```

## __construct()

```php
public function __construct(
    string $basePath,
    string $routesPath,
    array $providers,
    array $middleware,
    ?string $routesCachePath = null,
)
```

Every parameter is promoted to a public readonly property of the same name:

| Property | Type | Description |
|----------|------|-------------|
| `$basePath` | `string` | Application root. Not used by the kernel; available to your services through `Config` in the container |
| `$routesPath` | `string` | PHP file that returns a callable receiving a `Solo\Router\RouteCollector` and the container. Found the way `require` finds files |
| `$providers` | `array<class-string>` | Classes with a public `register(WritableContainerInterface $container)` method, run in order |
| `$middleware` | `array<MiddlewareInterface\|callable\|string>` | Global middleware, outermost first: middleware instances, callables or container IDs |
| `$routesCachePath` | `?string` | Absolute path of a PHP file that keeps the built router, or `null` to build it on every boot. Delete the file whenever routes change |

The constructor does not check the values: a missing routes file or an invalid provider makes the `Application` constructor throw.

```php
use Solo\Application\Config;
use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

$config = new Config(
    basePath: __DIR__,
    routesPath: __DIR__ . '/app/routes.php',
    providers: [HttpServiceProvider::class],
    middleware: [RoutingErrorMiddleware::class, RoutingMiddleware::class],
    routesCachePath: __DIR__ . '/var/cache/routes.php',
);

$config->routesCachePath; // '/…/var/cache/routes.php'
```

`Application` registers the instance in the container as `Solo\Application\Config`.
