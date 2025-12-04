<?php

declare(strict_types=1);

namespace Solo\Application;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RouteDispatcher implements RequestHandlerInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ResponseFactoryInterface $responseFactory
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $request->getAttribute('handler');
        $params = $request->getAttribute('params', []);

        if ($handler === null) {
            return $this->responseFactory->createResponse(404);
        }

        $response = $this->responseFactory->createResponse();

        return $this->invoke($handler, $request, $response, $params);
    }

    private function invoke(
        mixed $handler,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params
    ): ResponseInterface {
        // [Controller::class, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);
            return $controller->$method($request, $response, $params);
        }

        // Invokable class
        if (is_string($handler)) {
            $controller = $this->container->get($handler);
            return $controller($request, $response, $params);
        }

        // Callable
        if (is_callable($handler)) {
            return $handler($request, $response, $params);
        }

        throw new InvalidArgumentException('Invalid route handler');
    }
}
