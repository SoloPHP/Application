<?php

declare(strict_types=1);

namespace Solo\Application\Exception;

/**
 * A route parameter decodes to a control character such as NUL or a line break: 400 Bad Request.
 */
final class InvalidRouteParameterException extends RoutingException
{
    public function __construct(string $method, string $path, string $parameter)
    {
        parent::__construct(
            sprintf(
                'Route parameter "%s" in %s %s decodes to a control character',
                $parameter,
                self::printable($method),
                $path,
            ),
            400,
        );
    }
}
