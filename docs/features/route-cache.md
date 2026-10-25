# Route Cache

Without a cache, every boot registers the routes and compiles their patterns into the router — under PHP-FPM, on every request. Set `routesCachePath` to keep the built router in a PHP file instead:

```php
<?php

use Solo\Application\Application;
use Solo\Application\Config;

$app = new Application(new Config(
    basePath: $basePath,
    routesPath: $basePath . '/app/routes.php',
    providers: require $basePath . '/app/providers.php',
    middleware: require $basePath . '/app/middleware.php',
    routesCachePath: $basePath . '/var/cache/routes.php',
));
```

The first boot runs `routes.php`, builds the router and writes the file, creating its directory if needed. Later boots load the router from the file — from OPcache when it is enabled — without running `routes.php` or even looking for it. Solo Router measures loading a cached router at under 2 µs, against 1.2–2.8 ms to build 1,000 routes; see its [performance page](https://solophp.github.io/Router/guide/performance).

## Rules

- The file is not compared with `routes.php`: delete it whenever the routes change, for example in your deploy script, and leave `routesCachePath` unset during development.
- Handlers, middleware and attributes have to be plain values: strings, numbers, booleans, `null`, arrays and enums, such as `[UserController::class, 'show']` or `AuthMiddleware::class`. A closure or an object makes the constructor throw `LogicException` naming the route, and no file is written.
- Keep the file outside the web root, in a directory only the application can write: it is executed as PHP.
- Use an absolute path: a relative one is resolved against the working directory, which differs between PHP-FPM and the CLI, so each would keep a file of its own.
- Keep `routes.php` to route definitions: it runs only when the file is written, so anything else it does is skipped on later boots.

```php
<?php

use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    $r->get('/users/{id}', [UserController::class, 'show']);         // Cacheable
    $r->get('/health', HealthCheck::class)->middleware('auth');      // Cacheable
    $r->get('/ping', fn($request, $response) => $response);          // LogicException
    $r->get('/admin', AdminController::class)
        ->attribute('policy', new AdminPolicy());                     // LogicException
};
```

If the file cannot be written, the constructor throws `RuntimeException`.

## Clearing the cache

Delete the file when you deploy new routes:

```bash
rm -f var/cache/routes.php
```

The next request builds the router and writes the file again. Deleting is enough even when OPcache runs with `opcache.validate_timestamps=0`: the request that writes the new file also invalidates it in OPcache.

From PHP, `Solo\Router\RouteCache` deletes the file and invalidates it in the OPcache of the current process:

```php
<?php

use Solo\Router\RouteCache;

(new RouteCache('/var/www/app/var/cache/routes.php'))->clear();
```

## Writing the cache on deploy

The first request after a deploy pays for building the router. To write the file ahead of time, construct the application once from a deploy script, with the same configuration as the front controller:

```php
<?php
// bin/warm-route-cache.php

use Solo\Application\Application;
use Solo\Router\RouteCache;

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../app/config.php'; // returns the Config the front controller uses

(new RouteCache($config->routesCachePath))->clear();
new Application($config); // runs routes.php and writes the cache file
```

- The CLI cannot invalidate PHP-FPM's OPcache. With `opcache.validate_timestamps=0`, reload PHP-FPM after writing the file, as you do after a code change, or PHP-FPM keeps serving the old routes.
- The file must be readable by the PHP-FPM user.
- OPcache does not store a file modified less than `opcache.file_update_protection` seconds ago, 2 by default: see [concurrency and OPcache](https://solophp.github.io/Router/features/caching#concurrency-and-opcache) in the Solo Router documentation.

## OPcache

Enable OPcache in production. Without it, PHP parses the cache file on every request, which is still faster than building the router but much slower than loading it from OPcache.

See the [Solo Router cache guide](https://solophp.github.io/Router/features/caching) for the file format, atomic writes and storing the router elsewhere.
