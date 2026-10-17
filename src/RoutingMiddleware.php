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
use Solo\Contracts\Router\AllowedMethodsInterface;
use Solo\Contracts\Router\RouterInterface;

/**
 * Matches the request, stores the route in request attributes and runs the route's
 * own middleware (group middleware first) before passing the request on.
 *
 * Throws a RoutingException for a request it cannot route; telling 405 from 404 needs
 * a router that implements AllowedMethodsInterface.
 */
final readonly class RoutingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouterInterface $router,
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
        $path = self::decodeNonAscii($sentPath);
        $match = $this->router->match($method, $path);

        if ($match === false) {
            $allowed = $this->router instanceof AllowedMethodsInterface ? $this->router->getAllowedMethods($path) : [];

            if ($allowed === []) {
                throw new RouteNotFoundException($method, $sentPath);
            }

            throw new MethodNotAllowedException($method, $sentPath, $allowed);
        }

        $params = array_map(rawurldecode(...), $match['params']);

        foreach ($params as $name => $value) {
            if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new InvalidRouteParameterException($method, $sentPath, (string) $name);
            }
        }

        $request = $request
            ->withAttribute(RequestAttribute::HANDLER, $match['handler'])
            ->withAttribute(RequestAttribute::PARAMS, $params)
            ->withAttribute(RequestAttribute::ROUTE_NAME, $match['name'] ?? null)
            ->withAttribute(RequestAttribute::ROUTE_ATTRIBUTES, $match['attributes'] ?? []);

        return (new MiddlewarePipeline($this->container, $handler))
            ->addFromArray($match['middlewares'])
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
