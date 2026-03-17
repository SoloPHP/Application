<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Application\MiddlewarePipeline;

final class MiddlewarePipelineTest extends TestCase
{
    private ContainerInterface $container;
    private RequestHandlerInterface $finalHandler;
    private ServerRequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->finalHandler = $this->createMock(RequestHandlerInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testHandleWithNoMiddlewareCallsFinalHandler(): void
    {
        $this->finalHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $result = $pipeline->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleWithMiddleware(): void
    {
        $middlewareResponse = $this->createMock(ResponseInterface::class);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware
            ->expects($this->once())
            ->method('process')
            ->with($this->request, $this->isInstanceOf(RequestHandlerInterface::class))
            ->willReturn($middlewareResponse);

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add($middleware);

        $result = $pipeline->handle($this->request);

        $this->assertSame($middlewareResponse, $result);
    }

    public function testMiddlewareChainCallsFinalHandler(): void
    {
        $this->finalHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn($this->response);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware
            ->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($request, $handler) {
                return $handler->handle($request);
            });

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add($middleware);

        $result = $pipeline->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testAddFromArrayAddsMultipleMiddlewares(): void
    {
        $this->finalHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn($this->response);

        $callOrder = [];

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware1
            ->method('process')
            ->willReturnCallback(function ($request, $handler) use (&$callOrder) {
                $callOrder[] = 1;
                return $handler->handle($request);
            });

        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware2
            ->method('process')
            ->willReturnCallback(function ($request, $handler) use (&$callOrder) {
                $callOrder[] = 2;
                return $handler->handle($request);
            });

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->addFromArray([$middleware1, $middleware2]);

        $pipeline->handle($this->request);

        $this->assertSame([1, 2], $callOrder);
    }

    public function testHandleResolvesMiddlewareFromContainer(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware
            ->expects($this->once())
            ->method('process')
            ->willReturn($this->response);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('App\\Middleware\\TestMiddleware')
            ->willReturn($middleware);

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add('App\\Middleware\\TestMiddleware');

        $result = $pipeline->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleThrowsExceptionForInvalidMiddleware(): void
    {
        $this->container
            ->method('get')
            ->willReturn(new \stdClass());

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add('InvalidMiddleware');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware must implement MiddlewareInterface');

        $pipeline->handle($this->request);
    }
}
