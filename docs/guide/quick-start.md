# Quick Start

This page builds a small JSON application: a front controller, three configuration files, a service provider and a controller. It assumes the packages from [installation](/guide/installation):

```bash
composer require "solophp/application:^4.0.1" solophp/container solophp/http-emitter nyholm/psr7 nyholm/psr7-server
```

## Layout

```
my-app/
├── app/
│   ├── middleware.php
│   ├── providers.php
│   └── routes.php
├── public/
│   └── index.php
├── src/
│   ├── Controller/
│   │   └── UserController.php
│   └── Providers/
│       └── HttpServiceProvider.php
└── composer.json
```

Classes in `src/` are autoloaded under `App\`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
```

Run `composer dump-autoload` after adding the section.

## Front controller

`public/index.php` creates the application once and runs it:

```php
<?php

use Solo\Application\Application;
use Solo\Application\Config;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

$app = new Application(new Config(
    basePath: $basePath,
    routesPath: $basePath . '/app/routes.php',
    providers: require $basePath . '/app/providers.php',
    middleware: require $basePath . '/app/middleware.php',
));

$app->run();
```

The constructor creates a `Solo\Container\Container`, runs the providers and builds the router from `routes.php`. `run()` takes the request from the container, passes it through the middleware to the controller and emits the response.

## Service provider

`Application` needs three services from you: a PSR-17 response factory, the incoming request and an emitter. Register them in a provider:

```php
<?php

namespace App\Providers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\HttpEmitter\Emitter;

final class HttpServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(Psr17Factory::class, fn() => new Psr17Factory());

        $container->set(
            ResponseFactoryInterface::class,
            fn(ContainerInterface $c) => $c->get(Psr17Factory::class),
        );

        $container->set(ServerRequestInterface::class, function (ContainerInterface $c) {
            $factory = $c->get(Psr17Factory::class);
            return (new ServerRequestCreator($factory, $factory, $factory, $factory))
                ->fromGlobals();
        });

        $container->set(EmitterInterface::class, fn() => new Emitter());
    }
}
```

List it in `app/providers.php`:

```php
<?php

use App\Providers\HttpServiceProvider;

return [
    HttpServiceProvider::class,
];
```

## Middleware

`app/middleware.php` lists the global middleware, outermost first. `RoutingMiddleware` matches the route and has to come last; `RoutingErrorMiddleware` right before it answers requests that match no route:

```php
<?php

use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

return [
    RoutingErrorMiddleware::class,
    RoutingMiddleware::class,
];
```

## Routes

`app/routes.php` returns a callable that receives the `RouteCollector`. Routes are defined with the [Solo Router](https://solophp.github.io/Router/guide/quick-start) API:

```php
<?php

use App\Controller\UserController;
use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    $r->get('/users', [UserController::class, 'index'])->name('users.index');
    $r->get('/users/{id:[0-9]+}', [UserController::class, 'show'])->name('users.show');
};
```

## Controller

A handler receives the request, a new response from the response factory and the route parameters, and returns a response. The controller itself comes from the container, so its constructor can take dependencies:

```php
<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController
{
    private const USERS = [1 => 'Ada', 2 => 'Grace'];

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        return $this->json($response, self::USERS);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $id = (int) $params['id'];

        if (!isset(self::USERS[$id])) {
            return $this->json($response->withStatus(404), ['error' => 'User not found']);
        }

        return $this->json($response, ['id' => $id, 'name' => self::USERS[$id]]);
    }

    private function json(ResponseInterface $response, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

## Run it

Start PHP's built-in server:

```bash
php -S localhost:8000 -t public
```

| Request | Response |
|---------|----------|
| `GET /users/1` | `200` with `{"id":1,"name":"Ada"}` |
| `GET /users/3` | `404` from the controller, with `{"error":"User not found"}` |
| `GET /users/abc` | `404` with an empty body: no route matches |
| `DELETE /users/1` | `405` with an empty body and an `Allow` header listing `GET` and `HEAD` |
| `HEAD /users/1` | `200` from the `GET` route, without a body |

The last three come from routing: `RoutingMiddleware` throws a [routing exception](/features/routing-errors) and `RoutingErrorMiddleware` turns it into an empty response.

## Next steps

- [Lifecycle](/guide/lifecycle) — The order of bootstrap and request handling
- [Configuration](/guide/configuration) — Every `Config` option and file
- [Routing](/features/routing) — Route middleware, attributes and paths
- [Routing errors](/features/routing-errors) — Rendering `404` and `405` in your own format
- [Route cache](/features/route-cache) — Skipping route registration in production
