# Configuration

`Solo\Application\Config` is an immutable DTO passed to the `Application` constructor:

```php
<?php

use Solo\Application\Config;
use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

$config = new Config(
    basePath:   '/var/www/app',
    routesPath: '/var/www/app/app/routes.php',
    providers:  [HttpServiceProvider::class, DatabaseServiceProvider::class],
    middleware: [ErrorHandlerMiddleware::class, JsonParserMiddleware::class, RoutingErrorMiddleware::class, RoutingMiddleware::class],
    routesCachePath: '/var/www/app/var/cache/routes.php', // optional, see Route cache
);
```

| Option | Type | Description |
|--------|------|-------------|
| `basePath` | `string` | Application root. The kernel does not use it; your services can read it from `Config` |
| `routesPath` | `string` | PHP file that returns a callable receiving the route collector |
| `providers` | `array<class-string>` | Classes with a public `register()` method, run in list order |
| `middleware` | `array<MiddlewareInterface\|callable\|string>` | Global middleware, outermost first |
| `routesCachePath` | `?string` | Absolute path of the route cache file, or `null` (default) to build the router on every boot |

`Config` is registered in the container, so a factory can read `basePath`:

```php
$container->set(
    TemplateRenderer::class,
    fn(ContainerInterface $c) => new TemplateRenderer($c->get(Config::class)->basePath . '/templates'),
);
```

## routes.php

`routesPath` points to a file that returns a callable. It receives a `Solo\Router\RouteCollector` and the container; `Application` builds the router from the collector once the callable returns:

```php
<?php

use App\Controller\UserController;
use App\Middleware\AuthMiddleware;
use Psr\Container\ContainerInterface;
use Solo\Router\RouteCollector;

return function (RouteCollector $r, ContainerInterface $container): void {
    $r->get('/api/users', [UserController::class, 'index'])->name('users.index');
    $r->get('/api/users/{id}', [UserController::class, 'show'])->name('users.show');

    $r->group('/api/admin', function (RouteCollector $r): void {
        $r->post('/users', [UserController::class, 'store'])
            ->attribute('permission', 'users.create');
    }, [AuthMiddleware::class]);
};
```

Route definitions, groups, parameters and optional segments follow [Solo Router 4](https://solophp.github.io/Router/guide/quick-start). How `Application` uses the result is described in [Routing](/features/routing).

- The file is loaded in its own scope, so `$this` is not available: reach services or `Config` through the container instead.
- `routesPath` is found the way `require` finds files, so a relative path is looked up in the include path. Use an absolute path.
- A missing file throws `RuntimeException`, and a file that does not return a callable throws `UnexpectedValueException`. Invalid routes, such as a duplicate name, throw `InvalidArgumentException` when the router is built. All three happen in the constructor.
- Keep the file to route definitions. With the [route cache](/features/route-cache) it runs only when the cache file is written, so services it registered would be missing on later boots, and decisions it took, such as routes behind a feature flag, are frozen in the cache.

### Splitting routes

The callable can pass the collector on, so routes can live in several files:

```php
<?php

use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    (require __DIR__ . '/routes/api.php')($r);
    (require __DIR__ . '/routes/admin.php')($r);
};
```

```php
<?php
// app/routes/admin.php

use App\Controller\Admin\DashboardController;
use App\Middleware\AuthMiddleware;
use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    $r->group('/admin', function (RouteCollector $r): void {
        $r->get('/dashboard', DashboardController::class)->name('admin.dashboard');
    }, [AuthMiddleware::class]);
};
```

## providers.php

A list of provider classes, run in order during bootstrap:

```php
<?php

return [
    HttpServiceProvider::class,
    DatabaseServiceProvider::class,
    LoggingServiceProvider::class,
];
```

See [Service Providers](/features/service-providers).

## middleware.php

Order matters: middleware execute top to bottom on the way in and bottom to top on the way out. An exception skips the way out of every middleware it passes, so middleware that add headers to responses, such as CORS, go before every middleware that turns exceptions into responses:

```php
<?php

use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

return [
    CorsMiddleware::class,
    ErrorHandlerMiddleware::class,
    JsonParserMiddleware::class,
    RoutingErrorMiddleware::class, // answers routing errors, see Routing errors
    RoutingMiddleware::class,      // matches the route and runs its middleware
];
```

`RoutingMiddleware` routes the request: list it once, after the middleware that should run for every request, including ones that match no route. Middleware listed after it run for matched routes only and can read the route. See [Middleware](/features/middleware) and [Routing Errors](/features/routing-errors).

## Required services

Your providers must register these services:

| Service ID | Description |
|------------|-------------|
| `Psr\Http\Message\ResponseFactoryInterface` | PSR-17 response factory |
| `Psr\Http\Message\ServerRequestInterface` | The incoming request, used when `run()` is called without an argument |
| `Solo\Contracts\Http\EmitterInterface` | Sends the response to the client, used by `run()` |

`ContainerInterface`, `WritableContainerInterface`, `Config`, `Solo\Router\Router`, `RouteDispatcher`, `RoutingMiddleware` and `RoutingErrorMiddleware` are registered by `Application` itself; see [container services](/guide/lifecycle#container-services). Any middleware or controller class names referenced from configuration must also resolve.

## Sharing the configuration

When more than the front controller boots the application — a worker script, a deploy script, tests — keep the `Config` in a file of its own. `Config` takes plain values, so the file can also choose them per environment, for example to keep the route cache for production only:

```php
<?php
// app/config.php

use Solo\Application\Config;

$basePath = dirname(__DIR__);

return new Config(
    basePath: $basePath,
    routesPath: $basePath . '/app/routes.php',
    providers: require $basePath . '/app/providers.php',
    middleware: require $basePath . '/app/middleware.php',
    routesCachePath: getenv('APP_ENV') === 'production' ? $basePath . '/var/cache/routes.php' : null,
);
```

```php
<?php
// public/index.php

use Solo\Application\Application;

require __DIR__ . '/../vendor/autoload.php';

(new Application(require __DIR__ . '/../app/config.php'))->run();
```
