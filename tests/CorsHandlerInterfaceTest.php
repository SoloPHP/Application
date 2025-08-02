<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Application\CorsHandlerInterface;

class CorsHandlerInterfaceTest extends TestCase
{
    private CorsHandlerInterface $corsHandler;
    private ServerRequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->corsHandler = Mockery::mock(CorsHandlerInterface::class);
        $this->request = Mockery::mock(ServerRequestInterface::class);
        $this->response = Mockery::mock(ResponseInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testShouldApplyCorsMethod(): void
    {
        $this->corsHandler->shouldReceive('shouldApplyCors')
            ->with($this->request)
            ->andReturn(true);

        $result = $this->corsHandler->shouldApplyCors($this->request);
        $this->assertTrue($result);
    }

    public function testAddCorsHeadersMethod(): void
    {
        $modifiedResponse = Mockery::mock(ResponseInterface::class);

        $this->corsHandler->shouldReceive('addCorsHeaders')
            ->with($this->response, $this->request)
            ->andReturn($modifiedResponse);

        $result = $this->corsHandler->addCorsHeaders($this->response, $this->request);
        $this->assertSame($modifiedResponse, $result);
    }
}
