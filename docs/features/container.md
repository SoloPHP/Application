# Custom Container

By default the application creates a `Solo\Container\Container`. You can supply your own as the second constructor argument:

```php
<?php

use Solo\Application\Application;
use Solo\Container\Container;

$container = new Container();
// pre-register services if needed
$app = new Application($config, $container);
```

Without a container argument and without `solophp/container` installed, the constructor throws `RuntimeException`.

## Requirements

The container must implement `Solo\Contracts\Container\WritableContainerInterface`: PSR-11 `get()` and `has()`, plus `set()`:

```php
public function set(string $id, callable $factory): void;
```

- `set()` registers a factory. The factory receives the container when it runs.
- The interface expects shared services: the factory runs on the first `get()` of an ID, and later calls return the same instance. `Application` also works with a container that calls the factory on every `get()`, because it hands the router to `RoutingMiddleware` directly.
- `Application` calls `set()` only for services it has not resolved yet, so containers that refuse to replace a resolved service, like Pimple, work too.
- Middleware, controllers and invokable handlers are referenced by class name and resolved with `get()`. `solophp/container` creates any existing class by autowiring its constructor; with a container that does not, register every class you reference.

## Registration order

`Application` registers its [own services](/guide/lifecycle#container-services) in the constructor, then runs the providers. So:

- services you register before the constructor can be replaced by providers, and the IDs `Application` registers itself, such as `Config` or `RoutingErrorMiddleware`, are always replaced;
- to override one of those, or a provider's service, call `set()` after the constructor, before the first request: `$app->container->set(...)`.

`$app->container` is the container the application uses, whether you passed it or it was created.

## Adapting a read-only container

A PSR-11 container without `set()`, such as one compiled by your framework, can be wrapped. The adapter keeps the factories `Application` and your providers register and passes every other ID on:

```php
<?php

use Psr\Container\ContainerInterface;
use Solo\Contracts\Container\WritableContainerInterface;

final class WritableContainer implements WritableContainerInterface
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function __construct(private readonly ContainerInterface $inner)
    {
    }

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        return $this->inner->get($id);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || $this->inner->has($id);
    }
}
```

```php
$app = new Application($config, new WritableContainer($frameworkContainer));
```

Services built by the inner container take their dependencies from the inner container only: they do not see the factories registered on the adapter.
