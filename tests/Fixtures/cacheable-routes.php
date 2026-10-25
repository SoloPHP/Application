<?php

declare(strict_types=1);

use Solo\Application\Tests\Fixtures\TestController;
use Solo\Router\RouteCollector;

// Routes the route cache can export: handlers and middleware are strings or arrays. routes.php adds the rest
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
};
