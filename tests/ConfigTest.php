<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use PHPUnit\Framework\TestCase;
use Solo\Application\Config;
use Solo\Application\Tests\Fixtures\HttpServiceProvider;

final class ConfigTest extends TestCase
{
    public function testConfigStoresValues(): void
    {
        $config = new Config(
            basePath: '/app',
            routesPath: '/app/routes.php',
            providers: [HttpServiceProvider::class],
            middleware: ['Middleware1', 'Middleware2'],
            routesCachePath: '/app/var/routes.php',
        );

        $this->assertSame('/app', $config->basePath);
        $this->assertSame('/app/routes.php', $config->routesPath);
        $this->assertSame([HttpServiceProvider::class], $config->providers);
        $this->assertSame(['Middleware1', 'Middleware2'], $config->middleware);
        $this->assertSame('/app/var/routes.php', $config->routesCachePath);
    }

    public function testConfigWithEmptyArrays(): void
    {
        $config = new Config(
            basePath: '/var/www',
            routesPath: '/var/www/routes.php',
            providers: [],
            middleware: [],
        );

        $this->assertSame('/var/www', $config->basePath);
        $this->assertSame('/var/www/routes.php', $config->routesPath);
        $this->assertSame([], $config->providers);
        $this->assertSame([], $config->middleware);
        $this->assertNull($config->routesCachePath);
    }
}
