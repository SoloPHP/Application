<?php

declare(strict_types=1);

namespace Solo\Application;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Application\Exception\RouteNotFoundException;
use UnexpectedValueException;

final readonly class RouteDispatcher implements RequestHandlerInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ResponseFactoryInterface $responseFactory
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $request->getAttribute(RequestAttribute::HANDLER);

        if ($handler === null) {
            throw new RouteNotFoundException($request->getMethod(), RequestPath::sent($request));
        }

        $callable = CallableResolver::resolve($this->container, $handler);

        if (!is_callable($callable)) {
            throw new InvalidArgumentException(sprintf(
                'Route handler %s is not callable',
                CallableResolver::describe($handler)
            ));
        }

        $response = $callable(
            $request,
            $this->responseFactory->createResponse(),
            $request->getAttribute(RequestAttribute::PARAMS, []),
        );

        if (!$response instanceof ResponseInterface) {
            throw new UnexpectedValueException(sprintf(
                'Route handler %s must return %s, %s returned',
                CallableResolver::describe($handler),
                ResponseInterface::class,
                get_debug_type($response)
            ));
        }

        return $response;
    }
}
