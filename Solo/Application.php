<?php declare(strict_types=1);

namespace Solo;

use Solo\Router\RouteCollector;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use InvalidArgumentException;

/**
 * Main application class for handling routes, middlewares, and requests.
 */
final class Application extends RouteCollector implements RequestHandlerInterface
{
    /**
     * @var int Current index of the middleware pipeline.
     */
    private int $index = 0;

    /**
     * @var array List of middleware instances.
     */
    private array $middlewares = [];

    /**
     * @param ContainerInterface $container Dependency injection container for resolving services.
     * @param ResponseFactoryInterface $responseFactory PSR-17 Response Factory implementation (optional).
     */
    public function __construct(
        private readonly ContainerInterface       $container,
        private readonly ResponseFactoryInterface $responseFactory
    )
    {
    }

    /**
     * Adds middleware to the application pipeline.
     *
     * @param object|string $middleware Middleware class name or instance.
     * @throws ContainerExceptionInterface If there was an error while retrieving the service.
     * @throws NotFoundExceptionInterface If no entry was found for the identifier.
     * @throws InvalidArgumentException If the middleware is not valid.
     */
    public function addMiddleware(object|string $middleware): void
    {
        if (is_string($middleware)) {
            $middleware = $this->container->get($middleware);
        }

        if (!is_object($middleware)) {
            throw new InvalidArgumentException('Middleware must be an object or a class name.');
        }

        $this->middlewares[] = $middleware;
    }

    /**
     * Runs the application with the given request.
     *
     * @param ServerRequestInterface $request PSR-7 server request.
     * @return ResponseInterface PSR-7 response.
     * @throws ContainerExceptionInterface If there was an error while retrieving the service.
     * @throws NotFoundExceptionInterface If no entry was found for the identifier.
     * @throws InvalidArgumentException If the route handler is invalid.
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        $routeInfo = $this->matchRoute($request->getMethod(), $request->getUri()->getPath());

        if ($routeInfo === false) {
            return $this->responseFactory->createResponse(404);
        }

        array_map([$this, 'addMiddleware'], $routeInfo['middleware']);

        $request = $request->withAttribute('routeInfo', $routeInfo);

        return $this->handle($request);
    }

    /**
     * Handles the request through the middleware pipeline.
     *
     * @param ServerRequestInterface $request PSR-7 server request.
     * @return ResponseInterface PSR-7 response.
     * @throws ContainerExceptionInterface If there was an error while retrieving the service.
     * @throws NotFoundExceptionInterface If no entry was found for the identifier.
     * @throws InvalidArgumentException If the route handler is invalid.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middlewares[$this->index])) {
            return $this->routeDispatcher($request);
        }

        $this->index++;

        return $this->middlewares[$this->index - 1]->process($request, $this);
    }

    /**
     * Dispatches the route handler.
     *
     * @param ServerRequestInterface $request PSR-7 server request.
     * @return ResponseInterface PSR-7 response.
     * @throws InvalidArgumentException If the handler is invalid.
     * @throws ContainerExceptionInterface If there was an error while retrieving the service.
     * @throws NotFoundExceptionInterface If no entry was found for the identifier.
     */
    private function routeDispatcher(ServerRequestInterface $request): ResponseInterface
    {
        $routeInfo = $request->getAttribute('routeInfo');
        $response = $this->responseFactory->createResponse();
        $handler = $routeInfo['handler'];

        if (is_array($handler) && count($handler) === 2) {
            $controller = $this->container->get($handler[0]);
            $method = $handler[1];
            return $controller->$method($request, $response, $routeInfo['args']);
        }

        if (is_callable($handler)) {
            return $handler($request, $response, $routeInfo['args']);
        }

        throw new InvalidArgumentException('Invalid route handler.');
    }
}