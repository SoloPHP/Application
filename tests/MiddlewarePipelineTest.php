<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Application\MiddlewarePipeline;
use Solo\Application\Tests\Fixtures\RecordingHandler;
use Solo\Application\Tests\Fixtures\TraceMiddleware;
use UnexpectedValueException;

final class MiddlewarePipelineTest extends TestCase
{
    private ContainerInterface&Stub $container;
    private ServerRequestInterface $request;
    private RecordingHandler $finalHandler;

    protected function setUp(): void
    {
        $this->container = $this->createStub(ContainerInterface::class);
        $this->request = (new Psr17Factory())->createServerRequest('GET', '/');
        $this->finalHandler = new RecordingHandler();
    }

    public function testHandleWithNoMiddlewareCallsFinalHandler(): void
    {
        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->handle($this->request);

        $this->assertSame([$this->request], $this->finalHandler->requests);
    }

    public function testMiddlewareCanReturnItsOwnResponse(): void
    {
        $response = (new Psr17Factory())->createResponse(403);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware
            ->expects($this->once())
            ->method('process')
            ->with($this->request, $this->isInstanceOf(RequestHandlerInterface::class))
            ->willReturn($response);

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add($middleware);

        $this->assertSame($response, $pipeline->handle($this->request));
        $this->assertSame([], $this->finalHandler->requests);
    }

    public function testMiddlewareRunInOrderBeforeFinalHandler(): void
    {
        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->addFromArray([new TraceMiddleware('first'), new TraceMiddleware('second')]);

        $pipeline->handle($this->request);

        $this->assertSame(['first', 'second'], TraceMiddleware::trace($this->finalHandler->last()));
    }

    public function testAddIsFluent(): void
    {
        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);

        $this->assertSame($pipeline, $pipeline->add(new TraceMiddleware('a')));
        $this->assertSame($pipeline, $pipeline->addFromArray([new TraceMiddleware('b')]));
    }

    public function testHandleResolvesMiddlewareFromContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with('App\\Middleware\\TestMiddleware')
            ->willReturn(new TraceMiddleware('resolved'));

        $pipeline = new MiddlewarePipeline($container, $this->finalHandler);
        $pipeline->add('App\\Middleware\\TestMiddleware');

        $pipeline->handle($this->request);

        $this->assertSame(['resolved'], TraceMiddleware::trace($this->finalHandler->last()));
    }

    public function testMiddlewareIsNotResolvedUntilReached(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $pipeline = new MiddlewarePipeline($container, $this->finalHandler);
        $pipeline
            ->add(fn(): ResponseInterface => (new Psr17Factory())->createResponse(403))
            ->add('App\\Middleware\\NeverReached');

        $this->assertSame(403, $pipeline->handle($this->request)->getStatusCode());
    }

    public function testCallableMiddleware(): void
    {
        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add((new TraceMiddleware('closure'))->process(...));

        $pipeline->handle($this->request);

        $this->assertSame(['closure'], TraceMiddleware::trace($this->finalHandler->last()));
    }

    public function testContainerIdMayResolveToCallable(): void
    {
        $this->container->method('get')->willReturn((new TraceMiddleware('closure'))->process(...));

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add('closure.middleware');

        $pipeline->handle($this->request);

        $this->assertSame(['closure'], TraceMiddleware::trace($this->finalHandler->last()));
    }

    public function testCallableStringIsCalledWhenTheContainerDoesNotHaveIt(): void
    {
        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add(TraceMiddleware::class . '::mark');

        $pipeline->handle($this->request);

        $this->assertSame(['static'], TraceMiddleware::trace($this->finalHandler->last()));
    }

    public function testContainerIdTakesPrecedenceOverCallableString(): void
    {
        $id = TraceMiddleware::class . '::mark';
        $this->container->method('has')->willReturnMap([[$id, true]]);
        $this->container->method('get')->willReturnMap([[$id, new TraceMiddleware('container')]]);

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add($id);

        $pipeline->handle($this->request);

        $this->assertSame(['container'], TraceMiddleware::trace($this->finalHandler->last()));
    }

    public function testCallableMiddlewareMustReturnResponse(): void
    {
        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): void {
            $handler->handle($request);
        });

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Middleware Closure must return Psr\Http\Message\ResponseInterface, null returned'
        );

        $pipeline->handle($this->request);
    }

    public function testPipelineCanBeReused(): void
    {
        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add(new TraceMiddleware('a'));

        $pipeline->handle($this->request);
        $pipeline->handle($this->request);

        $this->assertSame([['a'], ['a']], array_map(TraceMiddleware::trace(...), $this->finalHandler->requests));
    }

    public function testMiddlewareCanCallNextMoreThanOnce(): void
    {
        $retry = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $handler->handle($request);
                return $handler->handle($request);
            }
        };

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add($retry)->add(new TraceMiddleware('inner'));

        $pipeline->handle($this->request);

        $this->assertSame(
            [['inner'], ['inner']],
            array_map(TraceMiddleware::trace(...), $this->finalHandler->requests),
        );
    }

    public function testHandleThrowsExceptionForInvalidMiddleware(): void
    {
        $this->container->method('get')->willReturn(new \stdClass());

        $pipeline = new MiddlewarePipeline($this->container, $this->finalHandler);
        $pipeline->add('InvalidMiddleware');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Middleware InvalidMiddleware must resolve to Psr\Http\Server\MiddlewareInterface or a callable, '
            . 'stdClass given'
        );

        $pipeline->handle($this->request);
    }
}
