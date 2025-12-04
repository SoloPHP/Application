# Solo Application

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![License](https://img.shields.io/packagist/l/solophp/application.svg)](https://github.com/solophp/application/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](https://github.com/solophp/application)

Application bootstrap with PSR-15 middleware pipeline.

A lightweight, PSR-compliant application kernel that wires together your container, router, middleware, and HTTP layer with zero configuration overhead.

## Features

- PSR-15 middleware pipeline
- PSR-11 container integration
- Service providers for dependency registration
- Flexible route handlers (controllers, invokables, callables)
- Zero framework lock-in — uses only PSR interfaces

## Installation

```bash
composer require solophp/application
```

## Requirements

- PHP 8.2+
- PSR-7 implementation (e.g., [nyholm/psr7](https://github.com/nyholm/psr7))
- PSR-11 compatible container with `set()` method

## Dependencies

The package depends only on **PSR interfaces** and **solophp/contracts**:

| Dependency | Purpose |
|------------|---------|
| psr/container | Container interface |
| psr/http-message | HTTP messages |
| psr/http-server-handler | Request handler |
| psr/http-server-middleware | Middleware |
| psr/http-factory | Response factory |
| solophp/contracts | WritableContainerInterface, EmitterInterface, RouterInterface |

## Usage

```php
// public/index.php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Solo\Application\Application;
use Solo\Application\Config;
use Solo\Dotenv\Dotenv;

$basePath = dirname(__DIR__);

Dotenv::load($basePath, ['.env', '.env.local']);

$config = new Config(
    basePath: $basePath,
    routesPath: $basePath . '/app/routes.php',
    providers: require $basePath . '/app/providers.php',
    middleware: require $basePath . '/app/middleware.php',
);

$app = new Application($config);
$app->run();
```

## Required Services

Your application must register these services via providers:

| Service | Interface |
|---------|-----------|
| Response Factory | `Psr\Http\Message\ResponseFactoryInterface` |
| Server Request | `Psr\Http\Message\ServerRequestInterface` |
| Router | `Solo\Contracts\Router\RouterInterface` |
| Emitter | `Solo\Contracts\Http\EmitterInterface` |

Example provider:

```php
<?php

namespace App\Providers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Container\Container;
use Solo\Contracts\Http\EmitterInterface;
use Solo\Contracts\Router\RouterInterface;
use Solo\HttpEmitter\Emitter;
use Solo\Router\RouteCollector;

final class HttpServiceProvider
{
    public function register(Container $container): void
    {
        $container->set(Psr17Factory::class, fn() => new Psr17Factory());

        $container->set(ResponseFactoryInterface::class,
            fn(ContainerInterface $c) => $c->get(Psr17Factory::class)
        );

        $container->set(ServerRequestInterface::class, function (ContainerInterface $c) {
            $factory = $c->get(Psr17Factory::class);
            return (new ServerRequestCreator($factory, $factory, $factory, $factory))
                ->fromGlobals();
        });

        $container->set(RouterInterface::class, fn() => new RouteCollector());
        $container->set(EmitterInterface::class, fn() => new Emitter());
    }
}
```

## Custom Container

You can provide your own container:

```php
$container = new MyContainer();
// ... configure container ...

$app = new Application($config, $container);
```

The container must implement `Solo\Contracts\Container\WritableContainerInterface`.

## Components

| Class | Description |
|-------|-------------|
| `Application` | Bootstrap and run the application |
| `Config` | Configuration DTO |
| `MiddlewarePipeline` | PSR-15 middleware pipeline |
| `RouteDispatcher` | Controller invocation |

## Request Flow

```
HTTP Request
      │
      ▼
Application.run()
      │
      ▼
MiddlewarePipeline
      │
      ▼
[Middleware 1] → [Middleware 2] → ... → [RoutingMiddleware]
      │
      ▼
RouteDispatcher → Controller
      │
      ▼
HTTP Response
```

## Configuration

### providers.php

```php
<?php

return [
    HttpServiceProvider::class,
    DatabaseServiceProvider::class,
    LoggingServiceProvider::class,
];
```

### middleware.php

```php
<?php

return [
    CorsMiddleware::class,
    ErrorHandlerMiddleware::class,
    JsonParserMiddleware::class,
    RoutingMiddleware::class,
];
```

### routes.php

```php
<?php

use Solo\Contracts\Router\RouterInterface;

return function (RouterInterface $r) {
    $r->addRoute('GET', '/api/users', [UserController::class, 'index']);
    $r->addRoute('POST', '/api/users', [UserController::class, 'store']);
};
```

## Service Providers

Service providers register dependencies in the container:

```php
<?php

namespace App\Providers;

use Solo\Container\Container;

final class DatabaseServiceProvider
{
    public function register(Container $container): void
    {
        $container->set(Connection::class, function () {
            return new Connection(/* ... */);
        });
    }
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
