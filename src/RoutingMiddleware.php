<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Contracts\Router\AllowedMethodsInterface;
use Solo\Contracts\Router\RouterInterface;

/**
 * Matches the request, stores the route in request attributes and runs the route's
 * own middleware (group middleware first) before passing the request on.
 *
 * Answers 404 when no route matches, 405 with an Allow header when the path exists for
 * other methods and the router can tell (AllowedMethodsInterface), and 400 when a route
 * parameter decodes to a control character such as NUL or a line break.
 */
final readonly class RoutingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouterInterface $router,
        private ContainerInterface $container,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = self::path($request);
        $match = $this->router->match($request->getMethod(), $path);

        if ($match === false) {
            return $this->notMatched($path);
        }

        $params = array_map(rawurldecode(...), $match['params']);

        foreach ($params as $value) {
            if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                return $this->responseFactory->createResponse(400);
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

    private function notMatched(string $path): ResponseInterface
    {
        $allowed = $this->allowedMethods($path);

        if ($allowed === []) {
            return $this->responseFactory->createResponse(404);
        }

        return $this->responseFactory->createResponse(405)->withHeader('Allow', $allowed);
    }

    /**
     * @return list<string>
     */
    private function allowedMethods(string $path): array
    {
        if ($this->router instanceof AllowedMethodsInterface) {
            return $this->router->getAllowedMethods($path);
        }

        // solophp/router 3.3 and 3.4 have the method but not the interface
        $getAllowedMethods = [$this->router, 'getAllowedMethods'];
        $allowed = is_callable($getAllowedMethods) ? $getAllowedMethods($path) : [];

        return is_array($allowed) ? array_values(array_filter($allowed, is_string(...))) : [];
    }

    /**
     * The path as the client sent it, with only percent-encoded non-ASCII bytes decoded: routes
     * like "/café" match, while escapes of ASCII characters such as "%2F", "%61" or "%00" stay,
     * so the router sees the same path structure as every other middleware. Parameters are
     * decoded after matching.
     */
    private static function path(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        if (str_contains($path, '%')) {
            $path = preg_replace_callback(
                '/(?:%[89A-Fa-f][0-9A-Fa-f])+/',
                static fn(array $bytes): string => rawurldecode($bytes[0]),
                $path
            ) ?? $path;
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }
}
