<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Application\Exception\RoutingException;

/**
 * Answers a RoutingException with an empty response of its status and headers. Listed right before
 * RoutingMiddleware, it lets every middleware in front of it see and decorate that response.
 */
final readonly class RoutingErrorMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (RoutingException $e) {
            return $e->applyTo($this->responseFactory->createResponse());
        }
    }
}
