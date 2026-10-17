<?php

declare(strict_types=1);

namespace Solo\Application\Exception;

/**
 * No route matches the request: 404 Not Found.
 */
final class RouteNotFoundException extends RoutingException
{
    public function __construct(string $method, string $path)
    {
        parent::__construct(sprintf('No route matches %s %s', self::printable($method), $path), 404);
    }
}
