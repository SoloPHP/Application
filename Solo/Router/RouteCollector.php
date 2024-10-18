<?php declare(strict_types=1);

namespace Solo\Router;

use Solo\Router;

class RouteCollector extends Router
{
    private string $group = '';
    private array $groupMiddleware = [];
    private int $lastIndex;

    /**
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     * @return self
     */
    public function get(string $path, $handler, array $middleware = []): self
    {
        $this->addRoute('GET', $this->group, $path, $handler, array_merge($middleware, $this->groupMiddleware));
        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }

    /**
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     * @return self
     */
    public function post(string $path, $handler, array $middleware = []): self
    {
        $this->addRoute('POST', $this->group, $path, $handler, array_merge($middleware, $this->groupMiddleware));
        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }

    /**
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     * @return self
     */
    public function put(string $path, $handler, array $middleware = []): self
    {
        $this->addRoute('PUT', $this->group, $path, $handler, array_merge($middleware, $this->groupMiddleware));
        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }

    /**
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     * @return self
     */
    public function patch(string $path, $handler, array $middleware = []): self
    {
        $this->addRoute('PATCH', $this->group, $path, $handler, array_merge($middleware, $this->groupMiddleware));
        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }

    /**
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     * @return self
     */
    public function delete(string $path, $handler, array $middleware = []): self
    {
        $this->addRoute('DELETE', $this->group, $path, $handler, array_merge($middleware, $this->groupMiddleware));
        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }

    public function name(string $name): void
    {
        $this->routes[$name] = $this->routes[$this->lastIndex];
        unset($this->routes[$this->lastIndex]);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousGroupPrefix = $this->group;
        $previousGroupMiddleware = $this->groupMiddleware;

        $this->group = $previousGroupPrefix . $prefix;
        $this->groupMiddleware = array_merge($middleware, $previousGroupMiddleware);

        $callback($this);

        $this->group = $previousGroupPrefix;
        $this->groupMiddleware = $previousGroupMiddleware;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

}