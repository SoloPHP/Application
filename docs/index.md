---
layout: home

hero:
  name: Solo Application
  text: PSR-15 Application Kernel
  tagline: Wires your container, middleware and routes into one request handler, with routing by Solo Router 4.
  image:
    src: /logo.svg
    alt: Solo Application
  actions:
    - theme: brand
      text: Get Started
      link: /guide/installation
    - theme: alt
      text: View on GitHub
      link: https://github.com/solophp/application

features:
  - icon: 🧅
    title: PSR-15 Pipeline
    details: Global middleware run outermost first and are resolved from the container only when a request reaches them. Class names, middleware objects and callables can be mixed.
  - icon: 🧭
    title: Built-in Routing
    details: RoutingMiddleware matches the route, runs group and route middleware, and puts the handler, parameters, route name and attributes on the request.
  - icon: ⚡
    title: Route Cache
    details: One config option keeps the built router in a PHP file that OPcache holds in memory, so a request skips registering and compiling routes.
  - icon: 🚦
    title: Routing Errors as Exceptions
    details: 404, 405 with Allow and 400 are thrown as RoutingException. Answer them with RoutingErrorMiddleware or render them in your own error middleware.
  - icon: 📦
    title: Service Providers
    details: Plain classes with a register() method fill the container. Use solophp/container or any container that implements WritableContainerInterface.
  - icon: 🎯
    title: Flexible Handlers
    details: Controller methods and invokable classes come from the container; closures and other callables are called as they are.
  - icon: 🔁
    title: Workers and Tests
    details: Application is itself a PSR-15 request handler. handle() returns the response without emitting it, for tests and worker runtimes.
  - icon: 🧩
    title: Built on Standards
    details: PSR-7, PSR-11, PSR-15 and PSR-17 interfaces, solophp/contracts for the container and the emitter, and solophp/router for routes.
---

<style>
/* On .VPHome, not :root: the build puts the theme's own :root defaults after this rule */
.VPHome {
  --vp-home-hero-name-color: transparent;
  --vp-home-hero-name-background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
  --vp-home-hero-image-background-image: linear-gradient(135deg, #ec489930 0%, #8b5cf630 100%);
  --vp-home-hero-image-filter: blur(44px);
}

.VPHero .VPImage {
  max-width: 200px;
  max-height: 200px;
}
</style>

## Version 4.0.1

Solo Application 4.0.1 requires PHP 8.3 or later. It creates the `RouteCollector`, calls your `routes.php` and builds the router with [Solo Router 4](https://solophp.github.io/Router/), keeps it in a [route cache](/features/route-cache) if you ask it to, and throws [routing errors](/features/routing-errors) instead of answering them with empty responses. Upgrading from 3.x? Read the [upgrade guide](/guide/upgrading).

4.0.0, published on 17 October 2026, is an earlier state of this release that still takes the router from the container: install 4.0.1 or later.

## Quick Example

```php
<?php
// public/index.php

use App\Providers\HttpServiceProvider;
use Solo\Application\Application;
use Solo\Application\Config;
use Solo\Application\RoutingErrorMiddleware;
use Solo\Application\RoutingMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application(new Config(
    basePath: dirname(__DIR__),
    routesPath: dirname(__DIR__) . '/app/routes.php',
    providers: [HttpServiceProvider::class],
    middleware: [RoutingErrorMiddleware::class, RoutingMiddleware::class],
));

$app->run();
```

```php
<?php
// app/routes.php

use App\Controller\UserController;
use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    $r->get('/users/{id}', [UserController::class, 'show'])->name('users.show');
};
```

The controller comes from the container and gets the request, a new response and the route parameters:

```php
<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController
{
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $response->getBody()->write("User {$params['id']}");

        return $response;
    }
}
```

`HttpServiceProvider` registers the PSR-17 factory, the incoming request and the emitter: see the [quick start](/guide/quick-start).

```bash
composer require "solophp/application:^4.0.1"
```

**Requirements:** PHP 8.3 or later, a PSR-7/PSR-17 implementation, a container and an HTTP emitter. See [installation](/guide/installation).

[Get started](/guide/quick-start) · [Lifecycle](/guide/lifecycle) · [API reference](/api/application) · [Upgrade guide](/guide/upgrading)
