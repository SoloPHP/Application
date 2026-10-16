<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Application\RequestAttribute;

final class TestController
{
    /**
     * @param array<string, string> $params
     */
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params
    ): ResponseInterface {
        $response->getBody()->write('user ' . $params['id']);
        return $response;
    }

    /**
     * Writes the middleware trace, e.g. "global>outer>handler".
     *
     * @param array<string, string> $params
     */
    public function trace(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params
    ): ResponseInterface {
        $response->getBody()->write(implode('>', [...TraceMiddleware::trace($request), 'handler']));
        return $response;
    }

    /**
     * Writes the route name and attributes as JSON.
     *
     * @param array<string, string> $params
     */
    public function route(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params
    ): ResponseInterface {
        $response->getBody()->write((string) json_encode([
            'name' => $request->getAttribute(RequestAttribute::ROUTE_NAME),
            'attributes' => $request->getAttribute(RequestAttribute::ROUTE_ATTRIBUTES),
        ]));
        return $response;
    }

    /**
     * @param array<string, string> $params
     */
    public static function ping(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params
    ): ResponseInterface {
        $response->getBody()->write('pong');
        return $response;
    }
}
