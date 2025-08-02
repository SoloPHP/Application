<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;
use Solo\Application\RouterInterface;

class RouterInterfaceTest extends TestCase
{
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->router = Mockery::mock(RouterInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testAddRouteMethod(): void
    {
        $handler = function () {
        };
        $middleware = ['TestMiddleware'];
        $page = 'test.page';

        $this->router->shouldReceive('addRoute')
            ->with('GET', '/test', $handler, $middleware, $page)
            ->once();

        $this->router->addRoute('GET', '/test', $handler, $middleware, $page);
        $this->assertTrue(true); // No exception thrown
    }

    public function testMatchRouteMethod(): void
    {
        $expectedRoute = [
            'method' => 'GET',
            'group' => '',
            'handler' => function () {
            },
            'args' => [],
            'middleware' => [],
            'page' => null
        ];

        $this->router->shouldReceive('matchRoute')
            ->with('GET', '/test')
            ->andReturn($expectedRoute);

        $result = $this->router->matchRoute('GET', '/test');
        $this->assertSame($expectedRoute, $result);
    }

    public function testMatchRouteReturnsFalseWhenNoMatch(): void
    {
        $this->router->shouldReceive('matchRoute')
            ->with('GET', '/not-found')
            ->andReturn(false);

        $result = $this->router->matchRoute('GET', '/not-found');
        $this->assertFalse($result);
    }

    public function testGetRoutesMethod(): void
    {
        $expectedRoutes = [
            [
                'method' => 'GET',
                'group' => '',
                'path' => '/test',
                'handler' => function () {
                },
                'middleware' => [],
                'page' => null
            ]
        ];

        $this->router->shouldReceive('getRoutes')
            ->andReturn($expectedRoutes);

        $result = $this->router->getRoutes();
        $this->assertSame($expectedRoutes, $result);
    }
}
