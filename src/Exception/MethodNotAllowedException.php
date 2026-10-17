<?php

declare(strict_types=1);

namespace Solo\Application\Exception;

/**
 * The path is routed for other methods only: 405 Method Not Allowed with an Allow header.
 */
final class MethodNotAllowedException extends RoutingException
{
    /**
     * @param list<string> $allowedMethods Empty for a resource that allows no method at the moment
     */
    public function __construct(string $method, string $path, private readonly array $allowedMethods)
    {
        parent::__construct(
            sprintf(
                'Method %s is not allowed for %s; allowed: %s',
                self::printable($method),
                $path,
                implode(', ', $allowedMethods),
            ),
            405,
            ['Allow' => $allowedMethods ?: ['']],
        );
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
