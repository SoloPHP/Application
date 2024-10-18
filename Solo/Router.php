<?php declare(strict_types=1);

namespace Solo;

class Router
{
    protected array $routes = [];

    /**
     * @param string $method
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     */
    public function addRoute(string $method, string $group, string $path, $handler, array $middleware = []): void
    {
        $this->routes[] = compact('method', 'group', 'path', 'handler', 'middleware');
    }

    /**
     * @param string $requestMethod
     * @param string $url
     * @return array|false
     */
    public function matchRoute(string $requestMethod, string $url)
    {
        foreach ($this->routes as ['method' => $method, 'group' => $group, 'path' => $path, 'handler' => $handler, 'middleware' => $middleware]) {
            if ($requestMethod === $method) {
                $pattern = str_replace('/', '\/', $group . $path);
                $pattern = preg_replace('/\[(?![^{]*})/', '(?:', $pattern);
                $pattern = preg_replace('/](?![^{]*})/', ')?', $pattern);
                $pattern = preg_replace('/{(\w+)(:([^}]+))?}/', '(?<$1>$3)', $pattern);
                $pattern = '/^' . $pattern . '$/';

                if (preg_match($pattern, $url, $matches)) {
                    $args = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    return compact(
                        'method',
                        'group',
                        'handler',
                        'args',
                        'middleware'
                    );
                }
            }
        }
        return false;
    }
}