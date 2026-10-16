<?php

declare(strict_types=1);

namespace Solo\Application;

/**
 * Names of the request attributes that RoutingMiddleware writes and RouteDispatcher reads.
 */
final class RequestAttribute
{
    /** Matched handler: [class-string, method], a container id or a callable */
    public const HANDLER = 'handler';

    /** Route parameters, percent-decoded, array<string, string> */
    public const PARAMS = 'params';

    /** Route name, or null for an unnamed route */
    public const ROUTE_NAME = 'route_name';

    /** Custom route attributes, array<string, mixed> */
    public const ROUTE_ATTRIBUTES = 'route_attributes';
}
