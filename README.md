# Solo Application

[![Latest Version](https://img.shields.io/badge/version-1.2.0-blue.svg)](https://github.com/solophp/application/releases)
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

$app = new Application($container, $responseFactory);

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
    ResponseFactoryInterface $responseFactory
)
```

- `$container` - PSR-11 container implementation
- `$responseFactory` - PSR-17 response factory implementation

## Middleware

Add middleware using the `addMiddleware()` method:

```php
$app->addMiddleware(CorsMiddleware::class);
$app->addMiddleware(new AuthMiddleware($container));
```

Middleware can be added as either:
- Class name (will be resolved through container)
- Object instance

Note: All middleware must be valid objects implementing middleware interface.

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

// Route with page attribute
$app->get('/blog/{slug}', [BlogController::class, 'show'], [], 'blog.show');

// Route group with common prefix and middleware
$app->group('/admin', function($app) {
    $app->get('/users', [AdminController::class, 'users']);
    $app->get('/settings', [AdminController::class, 'settings']);
}, [AdminAuthMiddleware::class]);
```

### Route Information

Inside your handlers, you can access the matched route information through the request's 'route' attribute:

```php
public function handle(ServerRequestInterface $request): ResponseInterface 
{
    $route = $request->getAttribute('route');
    // $route contains: method, group, handler, args, middleware, page
    
    // Example: access the page attribute
    $currentPage = $route['page'] ?? null;
    
    // Example: check current route group
    $isAdmin = str_starts_with($route['group'], '/admin');
    
    // ...
}
```

The route attribute contains all information about the matched route, including:
- `method` - HTTP method
- `group` - Route group prefix
- `handler` - Route handler
- `args` - Route parameters
- `middleware` - Route middleware
- `page` - Optional page identifier

## Exception Handling

The following exceptions may be thrown:

- `ContainerExceptionInterface` - Error retrieving service from container
- `NotFoundExceptionInterface` - Service not found in container
- `InvalidArgumentException` - Invalid route handler or middleware

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.