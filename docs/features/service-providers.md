# Service Providers

Providers fill the container during bootstrap. A provider is a plain class with a public `register()` method that receives the container; there is no interface to implement:

```php
<?php

namespace App\Providers;

use PDO;
use Psr\Container\ContainerInterface;
use Solo\Application\Config;
use Solo\Contracts\Container\WritableContainerInterface;

final class DatabaseServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(PDO::class, function (ContainerInterface $c) {
            $path = $c->get(Config::class)->basePath . '/var/app.sqlite';

            return new PDO('sqlite:' . $path);
        });
    }
}
```

List the providers in `Config::$providers`, usually through `app/providers.php`:

```php
<?php

use App\Providers\DatabaseServiceProvider;
use App\Providers\HttpServiceProvider;

return [
    HttpServiceProvider::class,
    DatabaseServiceProvider::class,
];
```

- Each provider is instantiated once, with `new` and no arguments, so its constructor cannot take dependencies. A class without a public `register()` method makes the constructor throw `InvalidArgumentException`.
- Providers run in list order, after `Application` has registered its [own services](/guide/lifecycle#container-services) and before the routes are loaded.
- `set()` registers a factory. The factory receives the container and runs when the service is first resolved, usually while a request is handled, not during bootstrap.

## HTTP services

Every application registers a PSR-17 response factory, the incoming request and an emitter. The [quick start](/guide/quick-start#service-provider) shows a provider for `nyholm/psr7` and `solophp/http-emitter`:

| Service ID | Used by |
|------------|---------|
| `Psr\Http\Message\ResponseFactoryInterface` | `RouteDispatcher` and `RoutingErrorMiddleware`, on every request |
| `Psr\Http\Message\ServerRequestInterface` | `run()` called without a request |
| `Solo\Contracts\Http\EmitterInterface` | `run()` |

An application that only calls `handle()`, such as a worker or a test, needs the response factory alone.

## Replacing services

With `solophp/container`, `set()` replaces an earlier factory for the same ID, so a later provider can override an earlier one. A provider can also replace the services `Application` registered before the providers ran, for example `RoutingErrorMiddleware`, to [change the fallback for routing errors](/features/routing-errors#the-fallback).

`Solo\Router\Router` is the exception: `Application` registers it after the providers, so it always holds `$app->router`.

## Services that need the router

`Application` registers the router as `Solo\Router\Router` once the providers have run, for services that need it, such as a URL generator using `getRouteByName()`. Take it in a factory, which runs later, and never resolve it inside `register()`:

```php
<?php

namespace App\Routing;

use InvalidArgumentException;
use Solo\Router\Router;

final class UrlGenerator
{
    public function __construct(private readonly Router $router)
    {
    }

    /**
     * Fills plain {name} parameters; routes with patterns or optional segments need more.
     *
     * @param array<string, string|int> $params
     */
    public function path(string $name, array $params = []): string
    {
        $route = $this->router->getRouteByName($name)
            ?? throw new InvalidArgumentException("No route named '$name'");

        $replacements = [];
        foreach ($params as $key => $value) {
            $replacements['{' . $key . '}'] = rawurlencode((string) $value);
        }

        return strtr($route->path, $replacements);
    }
}
```

```php
<?php

namespace App\Providers;

use App\Routing\UrlGenerator;
use Psr\Container\ContainerInterface;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Router\Router;

final class RoutingServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        // Runs on first use, when the router is registered
        $container->set(UrlGenerator::class, fn(ContainerInterface $c) => new UrlGenerator($c->get(Router::class)));
    }
}
```

For a route `/users/{id}` named `users.show`, `$urls->path('users.show', ['id' => 42])` returns `/users/42`. `solophp/container` would also autowire `UrlGenerator` without the factory. Solo Router has no URL generator of its own; see its note on [URL generation](https://solophp.github.io/Router/features/named-routes#url-generation) for what a complete one has to handle.

## Reading configuration

`Config` is in the container, so factories can read `basePath`, as `DatabaseServiceProvider` above does. Values that differ per environment, such as credentials, are usually read in the factory, for example with `getenv()`, so they are looked up only when the service is used.
