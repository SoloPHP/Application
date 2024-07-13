<?php declare(strict_types=1);

namespace Solo;

use Solo\Router\RouteCollector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use InvalidArgumentException;

class Application extends RouteCollector implements RequestHandlerInterface
{
    private int $index = 0;
    private array $middlewares = [];
    private Psr17Factory $psr17Factory;
    private ContainerInterface $container;

    public function __construct(
        ContainerInterface $container
    )
    {
        $this->container = $container;
        $this->psr17Factory = $this->container->get(Psr17Factory::class);
    }

    public function addMiddleware($middleware): void
    {
        $this->middlewares[] = is_string($middleware) ? new $middleware($this->container) : $middleware;
    }

    public function run(ServerRequestInterface $request): ResponseInterface
    {
        $routeInfo = $this->matchRoute($request->getMethod(), $request->getUri()->getPath());

        if ($routeInfo === false) {
            return $this->psr17Factory->createResponse(404);
        }

        array_map([$this, 'addMiddleware'], $routeInfo['middleware']);

        $request = $request->withAttribute('routeInfo', $routeInfo);

        return $this->handle($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middlewares[$this->index])) {
            return $this->routeDispatcher($request);
        }
        $this->index++;
        return $this->middlewares[$this->index - 1]->process($request, $this);
    }

    private function routeDispatcher(ServerRequestInterface $request): ResponseInterface
    {
        $routeInfo = $request->getAttribute('routeInfo');
        $response = $this->psr17Factory->createResponse();
        $handler = $routeInfo['handler'];

        if (is_array($handler) && count($handler) === 2) {
            $controller = $this->container->get($handler[0]);
            $method = $handler[1];
            return $controller->$method($request, $response, $routeInfo['args']);
        } elseif (is_callable($handler)) {
            return call_user_func($handler, $request, $response, $routeInfo['args']);
        } else {
            throw new InvalidArgumentException('Invalid route handler');
        }
    }
}