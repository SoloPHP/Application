# Solo Application

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![License](https://img.shields.io/packagist/l/solophp/application.svg)](https://github.com/solophp/application/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![Tests](https://github.com/solophp/application/actions/workflows/tests.yml/badge.svg)](https://github.com/solophp/application/actions/workflows/tests.yml)

PSR-15 application kernel that wires your container, middleware and routes into one request handler, with routing by [Solo Router 4](https://solophp.github.io/Router/).

> Full documentation: **[solophp.github.io/Application](https://solophp.github.io/Application/)**. Upgrading from 3.x? Read the [upgrade guide](https://solophp.github.io/Application/guide/upgrading).

## Features

- **PSR-15 pipeline** — Global middleware run outermost first and are resolved from the container only when a request reaches them
- **Built-in routing** — `RoutingMiddleware` matches the route, runs group and route middleware, and puts the handler, parameters, route name and attributes on the request
- **Routing errors as exceptions** — `404`, `405` with `Allow` and `400` are thrown as `RoutingException`; answer them with `RoutingErrorMiddleware` or render them in your own error middleware
- **Route cache** — The built router is kept in a PHP file that OPcache holds in memory, so a request skips registering and compiling routes
- **Service providers** — Plain classes with a `register()` method fill any container that implements `WritableContainerInterface`
- **Flexible handlers** — Controller methods and invokable classes from the container, or any callable
- **Workers and tests** — `Application` is a PSR-15 request handler: `handle()` returns the response, `run()` emits it

## Installation

```bash
composer require "solophp/application:^4.0.1"
```

Requires PHP 8.3 or later, a PSR-7/PSR-17 implementation, a container and, for `run()`, an HTTP emitter. The quick start uses these:

```bash
composer require solophp/container solophp/http-emitter nyholm/psr7 nyholm/psr7-server
```

See [installation](https://solophp.github.io/Application/guide/installation) for the requirements and dependencies.

## Quick Example

```php
<?php
// public/index.php

use App\Providers\HttpServiceProvider;
use Solo\Application\Application;
use Solo\Application\Config;
use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application(new Config(
    basePath: dirname(__DIR__),
    routesPath: dirname(__DIR__) . '/app/routes.php',
    providers: [HttpServiceProvider::class],
    middleware: [RoutingErrorMiddleware::class, RoutingMiddleware::class],
));

$app->run();
```

```php
<?php
// app/routes.php

use App\Controller\UserController;
use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    $r->get('/users/{id}', [UserController::class, 'show'])->name('users.show');
};
```

The controller comes from the container and gets the request, a new response and the route parameters:

```php
<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController
{
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $response->getBody()->write("User {$params['id']}");

        return $response;
    }
}
```

`HttpServiceProvider` registers the PSR-17 response factory, the incoming request and the emitter: see the [quick start](https://solophp.github.io/Application/guide/quick-start).

## Documentation

- [Quick start](https://solophp.github.io/Application/guide/quick-start)
- [Lifecycle](https://solophp.github.io/Application/guide/lifecycle)
- [Configuration](https://solophp.github.io/Application/guide/configuration)
- [Upgrading from 3.x](https://solophp.github.io/Application/guide/upgrading)
- [Routing](https://solophp.github.io/Application/features/routing)
- [Route cache](https://solophp.github.io/Application/features/route-cache)
- [Routing errors](https://solophp.github.io/Application/features/routing-errors)
- [Middleware](https://solophp.github.io/Application/features/middleware)
- [Service providers](https://solophp.github.io/Application/features/service-providers)
- [Route handlers](https://solophp.github.io/Application/features/handlers)
- [Workers and emitting](https://solophp.github.io/Application/features/workers)
- [Custom container](https://solophp.github.io/Application/features/container)
- [Testing](https://solophp.github.io/Application/features/testing)
- [API reference](https://solophp.github.io/Application/api/application)

## Development

```bash
composer install
composer check   # phpcs, phpstan, phpunit
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
