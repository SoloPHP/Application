<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Solo\Application\Exception\MethodNotAllowedException;
use Solo\Application\RoutingErrorMiddleware;
use Throwable;

final class RoutingErrorMiddlewareTest extends TestCase
{
    public function testAnswersRoutingExceptionWithEmptyResponse(): void
    {
        $response = $this->process(new MethodNotAllowedException('DELETE', '/users', ['GET', 'HEAD']));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, HEAD', $response->getHeaderLine('Allow'));
        $this->assertSame('', (string) $response->getBody());
    }

    public function testPassesResponseOn(): void
    {
        $response = (new Psr17Factory())->createResponse(201);

        $this->assertSame($response, $this->process($response));
    }

    public function testLetsOtherExceptionsThrough(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Handler failed');

        $this->process(new RuntimeException('Handler failed'));
    }

    private function process(ResponseInterface|Throwable $outcome): ResponseInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);

        if ($outcome instanceof Throwable) {
            $handler->method('handle')->willThrowException($outcome);
        } else {
            $handler->method('handle')->willReturn($outcome);
        }

        $factory = new Psr17Factory();

        return (new RoutingErrorMiddleware($factory))->process($factory->createServerRequest('GET', '/'), $handler);
    }
}
