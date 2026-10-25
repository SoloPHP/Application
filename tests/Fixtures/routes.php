<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Application\Tests\Fixtures\TestController;
use Solo\Router\RouteCollector;

return static function (RouteCollector $r): void {
    (require __DIR__ . '/cacheable-routes.php')($r);

    $r->get('/closure', [TestController::class, 'trace'])->middleware(
        fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            => $handler->handle($request)->withHeader('X-Closure', 'yes'),
    );

    $r->get('/object', [new TestController(), 'trace']);
    $r->get('/static', TestController::class . '::ping');

    // Known only to the outer container of DelegatingContainer
    $r->get('/fallback', [TestController::class, 'trace'])->middleware('trace.fallback');
};
