<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Application\RouteDispatcher;

final class RouteDispatcherTest extends TestCase
{
    private ContainerInterface $container;
    private ResponseFactoryInterface $responseFactory;
    private ServerRequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testHandleReturns404WhenNoHandler(): void
    {
        $notFoundResponse = $this->createMock(ResponseInterface::class);

        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['handler', null, null],
                ['params', [], []],
            ]);

        $this->responseFactory
            ->expects($this->once())
            ->method('createResponse')
            ->with(404)
            ->willReturn($notFoundResponse);

        $dispatcher = new RouteDispatcher($this->container, $this->responseFactory);
        $result = $dispatcher->handle($this->request);

        $this->assertSame($notFoundResponse, $result);
    }

    public function testHandleWithArrayHandler(): void
    {
        $controller = new class {
            public function action(
                ServerRequestInterface $request,
                ResponseInterface $response,
                array $params
            ): ResponseInterface {
                return $response;
            }
        };

        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['handler', null, [$controller::class, 'action']],
                ['params', [], ['id' => '123']],
            ]);

        $this->responseFactory
            ->method('createResponse')
            ->willReturn($this->response);

        $this->container
            ->method('get')
            ->with($controller::class)
            ->willReturn($controller);

        $dispatcher = new RouteDispatcher($this->container, $this->responseFactory);
        $result = $dispatcher->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleWithInvokableHandler(): void
    {
        $controller = new class {
            public function __invoke(
                ServerRequestInterface $request,
                ResponseInterface $response,
                array $params
            ): ResponseInterface {
                return $response;
            }
        };

        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['handler', null, $controller::class],
                ['params', [], []],
            ]);

        $this->responseFactory
            ->method('createResponse')
            ->willReturn($this->response);

        $this->container
            ->method('get')
            ->with($controller::class)
            ->willReturn($controller);

        $dispatcher = new RouteDispatcher($this->container, $this->responseFactory);
        $result = $dispatcher->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleWithCallableHandler(): void
    {
        $called = false;
        $handler = function ($request, $response, $params) use (&$called) {
            $called = true;
            return $response;
        };

        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['handler', null, $handler],
                ['params', [], []],
            ]);

        $this->responseFactory
            ->method('createResponse')
            ->willReturn($this->response);

        $dispatcher = new RouteDispatcher($this->container, $this->responseFactory);
        $result = $dispatcher->handle($this->request);

        $this->assertTrue($called);
        $this->assertSame($this->response, $result);
    }

    public function testHandleThrowsExceptionForInvalidHandler(): void
    {
        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['handler', null, 12345],
                ['params', [], []],
            ]);

        $this->responseFactory
            ->method('createResponse')
            ->willReturn($this->response);

        $dispatcher = new RouteDispatcher($this->container, $this->responseFactory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid route handler');

        $dispatcher->handle($this->request);
    }

    public function testHandlePassesParamsToHandler(): void
    {
        $receivedParams = null;
        $handler = function ($request, $response, $params) use (&$receivedParams) {
            $receivedParams = $params;
            return $response;
        };

        $expectedParams = ['id' => '42', 'slug' => 'test'];

        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['handler', null, $handler],
                ['params', [], $expectedParams],
            ]);

        $this->responseFactory
            ->method('createResponse')
            ->willReturn($this->response);

        $dispatcher = new RouteDispatcher($this->container, $this->responseFactory);
        $dispatcher->handle($this->request);

        $this->assertSame($expectedParams, $receivedParams);
    }
}
