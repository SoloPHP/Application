# Solo Application

[![Latest Version](https://img.shields.io/badge/version-1.3.0-blue.svg)](https://github.com/solophp/application/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

A PSR compliant application class with middleware support, routing capabilities, and CORS handling.

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
use Solo\Application\Application;
use Solo\Application\CorsHandlerInterface;
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
    ResponseFactoryInterface $responseFactory,
    ?CorsHandlerInterface $corsHandler = null
)
```

- `$container` - PSR-11 container implementation
- `$responseFactory` - PSR-17 response factory implementation
- `$corsHandler` - Optional CORS handler for API requests

## CORS Support

The application includes built-in CORS (Cross-Origin Resource Sharing) support through an optional CORS handler:

```php
use Solo\Application\Application;
use Solo\Application\CorsHandlerInterface;

// Create your CORS handler implementation
class MyCorsHandler implements CorsHandlerInterface
{
    public function shouldApplyCors(ServerRequestInterface $request): bool
    {
        // Your logic to determine if CORS should be applied
        return true;
    }

    public function addCorsHeaders(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        // Add appropriate CORS headers
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}

// Initialize application with CORS handler
$corsHandler = new MyCorsHandler();
$app = new Application($container, $responseFactory, $corsHandler);
```

The application automatically handles OPTIONS requests when a CORS handler is provided, returning appropriate CORS headers without requiring explicit route definitions.

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
 // Route with controller
$app->post('/api/users', [UserController::class, 'create']);

// Route with callable controller
$app->post('/api/users', UserController::class);

// Route with callback
$app->get('/api/users', function ($request, $response) {
    // Handle request
    return $response;
});

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

## CORS Handler Interface

If you need to implement custom CORS handling, implement the `CorsHandlerInterface`:

```php
use Solo\Application\CorsHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CustomCorsHandler implements CorsHandlerInterface
{
    public function shouldApplyCors(ServerRequestInterface $request): bool
    {
        // Implement your logic to determine when CORS should be applied
        // For example, check if request has an Origin header
        return $request->hasHeader('Origin');
    }

    public function addCorsHeaders(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        
        // Add CORS headers based on your requirements
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
```

## Exception Handling

The following exceptions may be thrown:

- `ContainerExceptionInterface` - Error retrieving service from container
- `NotFoundExceptionInterface` - Service not found in container
- `InvalidArgumentException` - Invalid route handler or middleware

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.