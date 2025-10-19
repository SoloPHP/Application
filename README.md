# Solo Application

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/application.svg)](https://packagist.org/packages/solophp/application)
[![License](https://img.shields.io/packagist/l/solophp/application.svg)](https://github.com/solophp/application/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/solophp/application.svg)](https://packagist.org/packages/solophp/application)


A PSR-compliant HTTP application class with middleware support, routing capabilities, and CORS handling.

## Requirements

- PHP 8.1 or higher
- PSR-7 HTTP message interfaces implementation
- PSR-11 container implementation
- PSR-17 HTTP factory implementation
- Router implementation that implements `Solo\Contracts\Router\RouterInterface`

## Installation

Install via Composer:

```bash
composer require solophp/application
```

## Dependencies

- [psr/container](https://github.com/php-fig/container) ^2.0
- [psr/http-message](https://github.com/php-fig/http-message) ^2.0
- [psr/http-server-handler](https://github.com/php-fig/http-server-handler) ^1.0
- [psr/http-server-middleware](https://github.com/php-fig/http-server-middleware) ^1.0
- [psr/http-factory](https://github.com/php-fig/http-factory) ^1.0
- [solophp/contracts](https://github.com/solophp/contracts) ^1.0

### Suggested

- [solophp/router](https://github.com/solophp/router) ^2.0 - Default router implementation

## Router

The application requires a router implementation that implements `Solo\Contracts\Router\RouterInterface`. You can use the suggested [solophp/router](https://github.com/solophp/router) package or provide your own implementation:

```bash
# Install the default router implementation
composer require solophp/router
```

## Basic Usage

```php
use Solo\Application\Application;
use Solo\Application\CorsHandlerInterface;
use Solo\Router\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// Create router implementation
$router = new RouteCollector();

// Create application with router
$app = new Application($router, $container, $responseFactory);

// Add routes directly to router
$router->get('/hello/{name}', function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
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
    RouterInterface $router,
    ContainerInterface $container,
    ResponseFactoryInterface $responseFactory,
    ?CorsHandlerInterface $corsHandler = null
)
```

- `$router` - Router implementation that implements `Solo\Contracts\Router\RouterInterface`
- `$container` - PSR-11 container implementation
- `$responseFactory` - PSR-17 response factory implementation
- `$corsHandler` - Optional CORS handler for API requests

## Router Interface

The application works with any router implementation that implements `Solo\Contracts\Router\RouterInterface`:

```php
interface RouterInterface
{
    /**
     * Adds a new route to the router.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Route path, without group prefix
     * @param callable|array|string $handler Route handler (callable or controller reference)
     * @param array{group?:string,middlewares?:array<int,callable>,name?:string} $options
     */
    public function addRoute(
        string $method,
        string $path,
        callable|array|string $handler,
        array $options = []
    ): void;

    /**
     * Matches the requested method and URL against registered routes.
     *
     * @param string $method HTTP method of the request
     * @param string $uri Requested URI
     * @return array{handler:callable|array|string,params:array<string,string>,middlewares:array<int,callable>}|false
     */
    public function match(string $method, string $uri): array|false;
}
```

This allows you to use any router implementation or create your own.

## CORS Support

The application includes built-in CORS (Cross-Origin Resource Sharing) support through an optional CORS handler:

### Using the Default CORS Handler

The package provides a ready-to-use `CorsHandler` implementation:

```php
use Solo\Application\Application;
use Solo\Application\CorsHandler;

// Basic CORS with default settings (allows all origins)
$corsHandler = new CorsHandler();
$app = new Application($router, $container, $responseFactory, $corsHandler);

// Custom CORS configuration
$corsHandler = new CorsHandler(
    allowedOrigins: ['http://localhost:3000', 'https://myapp.com'],
    allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With'],
    allowCredentials: true,
    maxAge: 3600
);
$app = new Application($router, $container, $responseFactory, $corsHandler);
```

### Custom CORS Handler

You can also implement your own CORS handler:

```php
use Solo\Application\CorsHandlerInterface;

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

$corsHandler = new MyCorsHandler();
$app = new Application($router, $container, $responseFactory, $corsHandler);
```

The application automatically handles OPTIONS requests when a CORS handler is provided, returning appropriate CORS headers without requiring explicit route definitions.

## Middleware

Add middleware using the `addMiddleware()` method:

```php
$app->addMiddleware(CorsMiddleware::class);
$app->addMiddleware(new AuthMiddleware($container));

// Factory function (receives container, returns middleware instance)
$app->addMiddleware(function ($container) {
    return new RateLimitMiddleware(
        $container->get(CacheInterface::class),
        maxRequests: 100
    );
});
```

Middleware can be added as:
- Class name (will be resolved through container)
- Object instance
- Callable factory (receives container, must return middleware instance)

Note: All middleware must be valid objects implementing middleware interface.

## Routing

The application provides convenient routing methods that delegate to the router implementation:

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
```

### Route Parameters

Inside your handlers, you can access the matched route parameters through the request's attributes:

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    $handler = $request->getAttribute('handler');
    $params = $request->getAttribute('params', []);

    // Access route parameters
    $userId = $params['id'] ?? null;

    // ...
}
```

The request contains the following attributes after routing:
- `handler` - The matched route handler (callable, array, or string)
- `params` - Array of route parameters extracted from the URL

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

## Development

### Running Tests

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run code style check
composer cs

# Fix code style issues
composer cs-fix

# Run static analysis
composer stan

# Run all checks
composer check
```

## Exception Handling

The following exceptions may be thrown:

- `ContainerExceptionInterface` - Error retrieving service from container
- `NotFoundExceptionInterface` - Service not found in container
- `InvalidArgumentException` - Invalid route handler or middleware

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.