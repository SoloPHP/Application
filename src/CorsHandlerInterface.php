<?php
declare(strict_types=1);

namespace Solo\Application;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface CorsHandlerInterface
{
    /**
     * Determines if CORS headers should be applied to the request.
     *
     * @param ServerRequestInterface $request The server request.
     * @return bool True if CORS headers should be applied, false otherwise.
     */
    public function shouldApplyCors(ServerRequestInterface $request): bool;

    /**
     * Adds CORS headers to the response based on the request.
     *
     * @param ResponseInterface $response The response to which CORS headers will be added.
     * @param ServerRequestInterface $request The server request containing the origin and other details.
     * @return ResponseInterface The modified response with CORS headers added.
     */
    public function addCorsHeaders(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface;
}