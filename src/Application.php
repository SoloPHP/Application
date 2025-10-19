<?php

declare(strict_types=1);

namespace Solo\Application;

use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Contracts\Router\RouterInterface;

/**
 * Main application class for handling routes, middlewares, and requests.
 */
final class Application implements RequestHandlerInterface
{
    /**
     * @var int Current index of the middleware pipeline.
     */
    private int $index = 0;

    /**
     * @var array<int, MiddlewareInterface> List of middleware instances.
     */
    private array $middlewares = [];

    /**
     * Constructor for the Application class.
     *
     * @param RouterInterface $router Router implementation
     * @param ContainerInterface $container Dependency injection container.
     * @param ResponseFactoryInterface $responseFactory Factory for creating responses.
     * @param CorsHandlerInterface|null $corsHandler Optional CORS handler for API requests.
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ContainerInterface $container,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ?CorsHandlerInterface $corsHandler = null
    ) {
    }

    /**
     * Adds middleware to the application pipeline.
     *
     * @param MiddlewareInterface|string|callable $middleware Middleware class name, instance or factory.
     * @throws ContainerExceptionInterface If there was an error while retrieving the service.
     * @throws NotFoundExceptionInterface If no entry was found for the identifier.
     * @throws InvalidArgumentException If the middleware is not valid.
     */
    public function addMiddleware(MiddlewareInterface|string|callable $middleware): void
    {
        if (is_string($middleware)) {
            $middleware = $this->container->get($middleware);
        } elseif (is_callable($middleware) && !$middleware instanceof MiddlewareInterface) {
            $middleware = $middleware($this->container);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new InvalidArgumentException('Middleware must implement MiddlewareInterface.');
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
        if (
            $request->getMethod() === 'OPTIONS'
            && $this->corsHandler?->shouldApplyCors($request)
        ) {
            $response = $this->responseFactory->createResponse(200);
            return $this->corsHandler->addCorsHeaders($response, $request);
        }

        $match = $this->router->match($request->getMethod(), $request->getUri()->getPath());

        if ($match === false) {
            return $this->responseFactory->createResponse(404);
        }

        foreach ($match['middlewares'] as $middleware) {
            $this->addMiddleware($middleware);
        }

        $request = $request
            ->withAttribute('handler', $match['handler'])
            ->withAttribute('params', $match['params']);

        $response = $this->handle($request);

        if ($this->corsHandler?->shouldApplyCors($request)) {
            $response = $this->corsHandler->addCorsHeaders($response, $request);
        }

        return $response;
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

        $middleware = $this->middlewares[$this->index];
        $this->index++;

        return $middleware->process($request, $this);
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
        $handler = $request->getAttribute('handler');
        $params = $request->getAttribute('params', []);
        $response = $this->responseFactory->createResponse();

        if (is_string($handler)) {
            $controller = $this->container->get($handler);
            return $controller($request, $response, $params);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $method] = $handler;
            $controller = $this->container->get($controllerClass);
            return $controller->$method($request, $response, $params);
        }

        if (is_callable($handler)) {
            return $handler($request, $response, $params);
        }

        throw new InvalidArgumentException('Invalid route handler.');
    }
}
