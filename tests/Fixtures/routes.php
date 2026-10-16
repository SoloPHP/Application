<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Application\Tests\Fixtures\TestController;
use Solo\Router\RouteCollector;

return static function (RouteCollector $r): void {
    $r->get('/users/{id}', [TestController::class, 'show']);

    $r->group('/admin', function (RouteCollector $r): void {
        $r->group('/reports', function (RouteCollector $r): void {
            $r->get('/daily', [TestController::class, 'trace'])->middleware('trace.route');
        }, ['trace.inner']);
    }, ['trace.outer']);

    $r->group('/billing', function (RouteCollector $r): void {
        $r->get('/invoices/{id}', [TestController::class, 'route'])
            ->name('invoices.show')
            ->attribute('plan', 'pro');
    }, attributes: ['permission' => 'billing.view']);

    $r->get('/closure', [TestController::class, 'trace'])->middleware(
        fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            => $handler->handle($request)->withHeader('X-Closure', 'yes'),
    );

    $r->get('/object', [new TestController(), 'trace']);
    $r->get('/static', TestController::class . '::ping');

    // Known only to the outer container of DelegatingContainer
    $r->get('/fallback', [TestController::class, 'trace'])->middleware('trace.fallback');
};
