<?php

declare(strict_types=1);

namespace Solo\Application\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Solo\Contracts\Http\EmitterInterface;

final class SpyEmitter implements EmitterInterface
{
    public ?ResponseInterface $emitted = null;

    public function emit(ResponseInterface $response): void
    {
        $this->emitted = $response;
    }
}
