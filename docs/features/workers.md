# Workers and Emitting

`Application` implements `Psr\Http\Server\RequestHandlerInterface`. `handle()` runs the pipeline and returns the response without touching the emitter; `run()` emits it as well:

| Call | Request | Emits | For |
|------|---------|-------|-----|
| `run()` | Resolved from the container | Yes | PHP-FPM and other servers that start PHP for each request |
| `run($request)` | The one you pass | Yes | Worker runtimes that send output the usual way, such as FrankenPHP |
| `handle($request)` | The one you pass | No | Tests, and runtimes that take a PSR-7 response, such as RoadRunner |

```php
$app = new Application($config);

$response = $app->handle($request); // no EmitterInterface needed
```

An application that only calls `handle()` needs neither `ServerRequestInterface` nor `EmitterInterface` in the container, only `ResponseFactoryInterface`.

## Long-running processes

Worker runtimes create the application once and pass every request to it:

- Pass each request explicitly. Called without an argument, `run()` resolves `ServerRequestInterface` from the container, which a shared-instance container builds only once: every later request would get the first one again.
- The constructor runs once per worker: providers, `routes.php` and building the router cost nothing per request.
- Services the container shares live as long as the worker, middleware and controllers included. Keep request data out of their properties, and reset services that hold state, such as an identity map, between requests.

The examples below load the configuration from [`app/config.php`](/guide/configuration#sharing-the-configuration).

## FrankenPHP

In [worker mode](https://frankenphp.dev/docs/worker/), FrankenPHP fills the superglobals for each request and sends output as usual, so `run()` works with a request created from globals:

```php
<?php
// public/index.php

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Solo\Application\Application;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application(require __DIR__ . '/../app/config.php');

$factory = new Psr17Factory();
$requests = new ServerRequestCreator($factory, $factory, $factory, $factory);

$handler = static function () use ($app, $requests): void {
    $app->run($requests->fromGlobals());
};

$maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? 0);
for ($handled = 0; !$maxRequests || $handled < $maxRequests; ++$handled) {
    $keepRunning = frankenphp_handle_request($handler);
    gc_collect_cycles();

    if (!$keepRunning) {
        break;
    }
}
```

List an error middleware that turns exceptions into responses, so a failing request gets a `500` instead of ending the worker script.

## RoadRunner

[RoadRunner's PSR-7 worker](https://github.com/roadrunner-php/http) hands out requests and takes responses, so the worker calls `handle()`:

```php
<?php
// worker.php

use Nyholm\Psr7\Factory\Psr17Factory;
use Solo\Application\Application;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

require __DIR__ . '/vendor/autoload.php';

$app = new Application(require __DIR__ . '/app/config.php');

$worker = Worker::create();
$factory = new Psr17Factory();
$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

while (true) {
    try {
        $request = $psr7->waitRequest();
    } catch (Throwable) {
        $psr7->respond($factory->createResponse(400));
        continue;
    }

    if ($request === null) {
        break; // the worker has been asked to stop
    }

    try {
        $psr7->respond($app->handle($request));
    } catch (Throwable $e) {
        $psr7->respond($factory->createResponse(500));
        $worker->error((string) $e);
    }
}
```

## The emitter

`run()` resolves `Solo\Contracts\Http\EmitterInterface` after `handle()` returns, so a request can still replace it, for example with an emitter that streams the response. In a worker, the replacement stays in the container for the requests that follow.

[solophp/http-emitter](https://github.com/SoloPHP/HTTP-Emitter) sends the status line, the headers and the body with PHP's `header()` and output functions, and skips the body for statuses that have none, such as `204` and `304`.
