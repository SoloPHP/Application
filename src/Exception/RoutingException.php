<?php

declare(strict_types=1);

namespace Solo\Application\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * A request that reaches no route handler; the status is also the exception code.
 */
abstract class RoutingException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        string $message,
        int $statusCode,
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    /**
     * @return array<string, list<string>> Headers the response must carry, e.g. Allow for 405
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * The response with this status and these headers; its body and other headers stay.
     */
    public function applyTo(ResponseInterface $response): ResponseInterface
    {
        $response = $response->withStatus($this->getStatusCode());

        foreach ($this->getHeaders() as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        return $response;
    }

    /**
     * The value with control characters escaped, so a request method cannot break a log line.
     */
    protected static function printable(string $value): string
    {
        return addcslashes($value, "\0..\37\177");
    }
}
