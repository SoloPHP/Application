<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Application\CorsHandler;

class CorsHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testShouldApplyCorsWithOrigin(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('Origin')
            ->andReturn('http://localhost:3000');

        $corsHandler = new CorsHandler();
        $result = $corsHandler->shouldApplyCors($request);

        $this->assertTrue($result);
    }

    public function testShouldApplyCorsWithoutOrigin(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('Origin')
            ->andReturn('');

        $corsHandler = new CorsHandler();
        $result = $corsHandler->shouldApplyCors($request);

        $this->assertFalse($result);
    }

    public function testAddCorsHeadersWithAllowedOrigin(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('Origin')
            ->andReturn('http://localhost:3000');
        $request->shouldReceive('getMethod')
            ->andReturn('GET');

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('withHeader')
            ->with('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->andReturnSelf();

        $corsHandler = new CorsHandler(['http://localhost:3000']);
        $result = $corsHandler->addCorsHeaders($response, $request);

        $this->assertSame($response, $result);
    }

    public function testAddCorsHeadersWithWildcardOrigin(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('Origin')
            ->andReturn('http://any-origin.com');
        $request->shouldReceive('getMethod')
            ->andReturn('GET');

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('withHeader')
            ->with('Access-Control-Allow-Origin', '*')
            ->andReturnSelf();

        $corsHandler = new CorsHandler(); // Default allows all origins
        $result = $corsHandler->addCorsHeaders($response, $request);

        $this->assertSame($response, $result);
    }

    public function testAddCorsHeadersWithCredentials(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('Origin')
            ->andReturn('http://localhost:3000');
        $request->shouldReceive('getMethod')
            ->andReturn('GET');

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('withHeader')
            ->with('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->andReturnSelf();
        $response->shouldReceive('withHeader')
            ->with('Access-Control-Allow-Credentials', 'true')
            ->andReturnSelf();

        $corsHandler = new CorsHandler(
            allowedOrigins: ['http://localhost:3000'],
            allowCredentials: true
        );
        $result = $corsHandler->addCorsHeaders($response, $request);

        $this->assertSame($response, $result);
    }

    public function testAddCorsHeadersForOptionsRequest(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('Origin')
            ->andReturn('http://localhost:3000');
        $request->shouldReceive('getMethod')
            ->andReturn('OPTIONS');
        $request->shouldReceive('getHeaderLine')
            ->with('Access-Control-Request-Method')
            ->andReturn('POST');
        $request->shouldReceive('getHeaderLine')
            ->with('Access-Control-Request-Headers')
            ->andReturn('Content-Type');

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('withHeader')
            ->with('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->andReturnSelf();
        $response->shouldReceive('withHeader')
            ->with('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->andReturnSelf();
        $response->shouldReceive('withHeader')
            ->with('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->andReturnSelf();
        $response->shouldReceive('withHeader')
            ->with('Access-Control-Max-Age', '86400')
            ->andReturnSelf();

        $corsHandler = new CorsHandler(['http://localhost:3000']);
        $result = $corsHandler->addCorsHeaders($response, $request);

        $this->assertSame($response, $result);
    }

    public function testAddCorsHeadersWithDisallowedOrigin(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('Origin')
            ->andReturn('http://malicious-site.com');

        $response = Mockery::mock(ResponseInterface::class);

        $corsHandler = new CorsHandler(['http://localhost:3000']); // Only allow localhost
        $result = $corsHandler->addCorsHeaders($response, $request);

        $this->assertSame($response, $result);
    }
}
