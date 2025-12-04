<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use PHPUnit\Framework\TestCase;
use Solo\Application\Config;

final class ConfigTest extends TestCase
{
    public function testConfigStoresValues(): void
    {
        $config = new Config(
            basePath: '/app',
            routesPath: '/app/routes.php',
            providers: ['Provider1', 'Provider2'],
            middleware: ['Middleware1', 'Middleware2'],
        );

        $this->assertSame('/app', $config->basePath);
        $this->assertSame('/app/routes.php', $config->routesPath);
        $this->assertSame(['Provider1', 'Provider2'], $config->providers);
        $this->assertSame(['Middleware1', 'Middleware2'], $config->middleware);
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
    }
}
