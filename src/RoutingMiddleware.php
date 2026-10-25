<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Application\Exception\InvalidRouteParameterException;
use Solo\Application\Exception\MethodNotAllowedException;
use Solo\Application\Exception\RouteNotFoundException;
use Solo\Application\Exception\RoutingException;
use Solo\Router\Results\MethodNotAllowed;
use Solo\Router\Results\NotFound;
use Solo\Router\Router;

/**
 * Matches the request, stores the route in request attributes and runs the route's
 * own middleware (group middleware first) before passing the request on.
 *
 * Throws a RoutingException for a request it cannot route.
 */
final readonly class RoutingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Router $router,
        private ContainerInterface $container,
    ) {
    }

    /**
     * @throws RoutingException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $sentPath = RequestPath::sent($request);
        $result = $this->router->resolve($method, self::decodeNonAscii($sentPath));

        if ($result instanceof NotFound) {
            throw new RouteNotFoundException($method, $sentPath);
        }
        if ($result instanceof MethodNotAllowed) {
            throw new MethodNotAllowedException($method, $sentPath, $result->allowedMethods);
        }

        $params = array_map(rawurldecode(...), $result->params);

        foreach ($params as $name => $value) {
            if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new InvalidRouteParameterException($method, $sentPath, $name);
            }
        }

        $route = $result->route;
        $request = $request
            ->withAttribute(RequestAttribute::HANDLER, $route->handler)
            ->withAttribute(RequestAttribute::PARAMS, $params)
            ->withAttribute(RequestAttribute::ROUTE_NAME, $route->name)
            ->withAttribute(RequestAttribute::ROUTE_ATTRIBUTES, $route->attributes);

        return (new MiddlewarePipeline($this->container, $handler))
            ->addFromArray($route->middlewares)
            ->handle($request);
    }

    /**
     * Decodes only percent-encoded non-ASCII bytes: routes like "/café" match, while escapes of
     * ASCII characters such as "%2F", "%61" or "%00" stay, so the router sees the same path
     * structure as every other middleware. Parameters are decoded after matching.
     */
    private static function decodeNonAscii(string $path): string
    {
        if (!str_contains($path, '%')) {
            return $path;
        }

        return preg_replace_callback(
            '/(?:%[89A-Fa-f][0-9A-Fa-f])+/',
            static fn(array $bytes): string => rawurldecode($bytes[0]),
            $path
        ) ?? $path;
    }
}
