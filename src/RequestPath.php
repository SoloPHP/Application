<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The request path as the client sent it, with a leading slash: what routing matches on and reports.
 *
 * @internal
 */
final class RequestPath
{
    public static function sent(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }
}
