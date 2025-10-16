<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Default CORS handler implementation.
 */
final class CorsHandler implements CorsHandlerInterface
{
    /**
     * @param array<string> $allowedOrigins List of allowed origins
     *     (e.g., ['http://localhost:3000', 'https://example.com'])
     * @param array<string> $allowedMethods List of allowed HTTP methods
     *     (e.g., ['GET', 'POST', 'PUT', 'DELETE'])
     * @param array<string> $allowedHeaders List of allowed headers
     *     (e.g., ['Content-Type', 'Authorization'])
     * @param bool $allowCredentials Whether to allow credentials
     *     (cookies, authorization headers)
     * @param int $maxAge Cache duration for preflight requests in seconds
     */
    public function __construct(
        private readonly array $allowedOrigins = ['*'],
        private readonly array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private readonly array $allowedHeaders = ['Content-Type', 'Authorization'],
        private readonly bool $allowCredentials = false,
        private readonly int $maxAge = 86400
    ) {
    }

    public function shouldApplyCors(ServerRequestInterface $request): bool
    {
        $origin = $request->getHeaderLine('Origin');

        // Always apply CORS if origin is present
        return $origin !== '';
    }

    public function addCorsHeaders(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if ($origin === '') {
            return $response;
        }

        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return $response;
        }

        // Add CORS headers
        $response = $response->withHeader('Access-Control-Allow-Origin', $this->getAllowedOrigin($origin));

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $requestMethod = $request->getHeaderLine('Access-Control-Request-Method');
            $requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');

            if ($requestMethod !== '') {
                $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
            }

            if ($requestHeaders !== '') {
                $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
            }

            $response = $response->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }

        return $response;
    }

    /**
     * Check if the origin is allowed.
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Get the appropriate Access-Control-Allow-Origin value.
     */
    private function getAllowedOrigin(string $origin): string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return $this->allowCredentials ? $origin : '*';
        }

        return in_array($origin, $this->allowedOrigins, true) ? $origin : '';
    }
}
