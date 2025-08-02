<?php

declare(strict_types=1);

namespace Solo\Application;

/**
 * Interface for router implementations.
 * This allows Application to work with any router implementation.
 */
interface RouterInterface
{
    /**
     * Adds a new route to the router.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Route path
     * @param callable|array|string $handler Route handler
     * @param array $middleware Array of middleware
     * @param string|null $page Optional page identifier
     */
    public function addRoute(
        string $method,
        string $path,
        callable|array|string $handler,
        array $middleware = [],
        ?string $page = null
    ): void;

    /**
     * Matches the requested method and URL against registered routes.
     *
     * @param string $requestMethod HTTP method of the request
     * @param string $url Requested URL
     * @return array{
     *     method: string,
     *     group: string,
     *     handler: callable|array|string,
     *     args: array<string, string>,
     *     middleware: array<callable>,
     *     page: string|null
     * }|false
     */
    public function matchRoute(string $requestMethod, string $url): array|false;

    /**
     * Gets all registered routes.
     *
     * @return array<array{
     *     method: string,
     *     group: string,
     *     path: string,
     *     handler: callable|array|string,
     *     middleware: array<callable>,
     *     page: string|null
     * }>
     */
    public function getRoutes(): array;
}
