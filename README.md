# Solo Application

[![Latest Version](https://img.shields.io/github/release/solophp/application.svg)](https://github.com/solophp/application/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

A PSR compliant application class with middleware support and routing capabilities.

## Requirements

- PHP 8.1 or higher
- PSR-7 HTTP message interfaces implementation
- PSR-11 container implementation
- PSR-17 HTTP factory implementation

## Installation

Install via Composer:

```bash
composer require solophp/application
```

## Dependencies

- [psr/container](https://github.com/php-fig/container) ^2.0
- [psr/http-message](https://github.com/php-fig/http-message) ^2.0
- [psr/http-server-handler](https://github.com/php-fig/http-server-handler) ^1.0
- [psr/http-factory](https://github.com/php-fig/http-factory) ^1.0
- [solophp/router](https://github.com/solophp/router) ^1.0

## Basic Usage

```php
use Solo\Application;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

$app = new Application($container);

// Add route
$app->get('/hello/{name}', function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
    $response->getBody()->write("Hello, {$args['name']}!");
    return $response;
});

// Add middleware
$app->addMiddleware(SomeMiddleware::class);

// Run application
$response = $app->run($request);
```

## Constructor Options

```php
public function __construct(
    ContainerInterface $container,
    ?ResponseFactoryInterface $responseFactory = null
)
```

- `$container` - PSR-11 container implementation
- `$responseFactory` - Optional PSR-17 response factory. If not provided, will be retrieved from container.

## Middleware

Add middleware using the `addMiddleware()` method:

```php
$app->addMiddleware(CorsMiddleware::class);
$app->addMiddleware(new AuthMiddleware($container));
```

Middleware can be added as either:
- Class name (string)
- Object instance

Class name middleware will be instantiated via container.

## Routing

The application extends `RouteCollector` and provides standard routing methods:

```php
// Route with callback
$app->get('/api/users', function ($request, $response) {
    // Handle request
    return $response;
});

// Route with controller
$app->post('/api/users', [UserController::class, 'create']);

// Route with middleware
$app->get('/admin/dashboard', [DashboardController::class, 'index'])
    ->addMiddleware(AdminAuthMiddleware::class);
```

## Exception Handling

The following exceptions may be thrown:

- `ContainerExceptionInterface` - Error retrieving service from container
- `NotFoundExceptionInterface` - Service not found in container
- `InvalidArgumentException` - Invalid route handler

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.