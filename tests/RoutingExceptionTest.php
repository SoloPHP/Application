<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Solo\Application\Exception\InvalidRouteParameterException;
use Solo\Application\Exception\MethodNotAllowedException;
use RuntimeException;
use Solo\Application\Exception\RouteNotFoundException;
use Solo\Application\Exception\RoutingException;

final class RoutingExceptionTest extends TestCase
{
    public function testApplyToSetsStatusAndHeadersAndKeepsTheRest(): void
    {
        $response = (new Psr17Factory())->createResponse()->withHeader('Content-Type', 'application/json');
        $response->getBody()->write('{"error":405}');

        $applied = (new MethodNotAllowedException('DELETE', '/users', ['GET', 'HEAD']))->applyTo($response);

        $this->assertSame(405, $applied->getStatusCode());
        $this->assertSame('GET, HEAD', $applied->getHeaderLine('Allow'));
        $this->assertSame('application/json', $applied->getHeaderLine('Content-Type'));
        $this->assertSame('{"error":405}', (string) $applied->getBody());
    }

    public function testEachExceptionCarriesItsStatusAsCodeAndItsHeaders(): void
    {
        $notFound = new RouteNotFoundException('GET', '/missing');
        $notAllowed = new MethodNotAllowedException('DELETE', '/users', ['GET', 'HEAD']);
        $invalid = new InvalidRouteParameterException('GET', '/files/a%00b', 'name');

        $this->assertSame([404, 404, []], [$notFound->getStatusCode(), $notFound->getCode(), $notFound->getHeaders()]);
        $this->assertSame(
            [405, 405, ['Allow' => ['GET', 'HEAD']]],
            [$notAllowed->getStatusCode(), $notAllowed->getCode(), $notAllowed->getHeaders()],
        );
        $this->assertSame([400, 400, []], [$invalid->getStatusCode(), $invalid->getCode(), $invalid->getHeaders()]);
    }

    public function testNoAllowedMethodsGiveAnEmptyAllowHeader(): void
    {
        $exception = new MethodNotAllowedException('POST', '/disabled', []);

        $applied = $exception->applyTo((new Psr17Factory())->createResponse());

        $this->assertSame(405, $applied->getStatusCode());
        $this->assertTrue($applied->hasHeader('Allow'));
        $this->assertSame('', $applied->getHeaderLine('Allow'));
        $this->assertSame([], $exception->getAllowedMethods());
    }

    public function testMessagesNameTheRequestWithTheMethodEscaped(): void
    {
        $method = "GET\r\nX-Forged: 1";

        $this->assertSame(
            'No route matches GET\r\nX-Forged: 1 /users',
            (new RouteNotFoundException($method, '/users'))->getMessage(),
        );
        $this->assertSame(
            'Method GET\r\nX-Forged: 1 is not allowed for /users; allowed: POST',
            (new MethodNotAllowedException($method, '/users', ['POST']))->getMessage(),
        );
        $this->assertSame(
            'Route parameter "name" in GET\r\nX-Forged: 1 /files/a%00b decodes to a control character',
            (new InvalidRouteParameterException($method, '/files/a%00b', 'name'))->getMessage(),
        );
    }

    public function testSubclassCanChainThePreviousException(): void
    {
        $previous = new RuntimeException('Tenant lookup failed');

        $exception = new class ('Unknown tenant', 404, [], $previous) extends RoutingException {
        };

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(404, $exception->getStatusCode());
    }
}
