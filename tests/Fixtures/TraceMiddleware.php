<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Appends its name to the "trace" request attribute, so tests can see the order middleware ran in.
 */
final class TraceMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $name)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->withAttribute('trace', [...self::trace($request), $this->name]));
    }

    /**
     * The same as a "static" instance, for middleware given as a "Class::method" string.
     */
    public static function mark(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return (new self('static'))->process($request, $handler);
    }

    /**
     * @return list<string>
     */
    public static function trace(ServerRequestInterface $request): array
    {
        /** @var list<string> $trace written only by process() */
        $trace = $request->getAttribute('trace', []);

        return $trace;
    }
}
