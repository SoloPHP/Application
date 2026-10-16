<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Remembers the requests it handled and answers each with an empty 200 response.
 */
final class RecordingHandler implements RequestHandlerInterface
{
    /** @var list<ServerRequestInterface> */
    public array $requests = [];

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        return (new Psr17Factory())->createResponse();
    }

    public function last(): ServerRequestInterface
    {
        if ($this->requests === []) {
            throw new \LogicException('No request was handled');
        }

        return $this->requests[count($this->requests) - 1];
    }
}
