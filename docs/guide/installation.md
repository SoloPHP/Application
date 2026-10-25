# Installation

## Requirements

- PHP 8.3 or later within PHP 8.x (`^8.3`)
- Composer
- A PSR-7 and PSR-17 implementation, such as [nyholm/psr7](https://github.com/Nyholm/psr7)
- A container that implements `Solo\Contracts\Container\WritableContainerInterface`, such as [solophp/container](https://github.com/SoloPHP/Container)
- An HTTP emitter that implements `Solo\Contracts\Http\EmitterInterface`, such as [solophp/http-emitter](https://github.com/SoloPHP/HTTP-Emitter), if you send responses with `run()`

Projects on PHP 8.2 can stay on version 3.x.

## Composer

Install Solo Application 4:

```bash
composer require "solophp/application:^4.0.1"
```

[solophp/router](https://solophp.github.io/Router/) 4 comes with it: `Application` builds the router itself. These pages document version 4.0.1; when upgrading an existing application, follow the [upgrade guide](/guide/upgrading).

4.0.0, published on 17 October 2026, is an earlier state of this release: it takes a `solophp/router` 3.5 router from the container through `RouterInterface` and supports PHP 8.2. The `^4.0.1` constraint keeps Composer from picking it, for example on PHP 8.2 or next to `solophp/router` 3.

Then add the packages the application runs on. The [quick start](/guide/quick-start) uses these:

```bash
composer require solophp/container solophp/http-emitter nyholm/psr7 nyholm/psr7-server
```

| Package | Purpose |
|---------|---------|
| `solophp/container` | The container `Application` creates when you do not pass one |
| `solophp/http-emitter` | Sends the response to the client in `run()` |
| `nyholm/psr7` | PSR-7 messages and PSR-17 factories |
| `nyholm/psr7-server` | Creates the server request from PHP globals |

`solophp/container` 3 requires PHP 8.4. On PHP 8.3 Composer installs version 2, which `Application` uses the same way.

Without `solophp/container`, pass your own container to the constructor; otherwise it throws `RuntimeException`. See [Custom Container](/features/container).

## Dependencies

| Dependency | Purpose |
|------------|---------|
| `psr/container` | Container interface |
| `psr/http-message` | HTTP messages |
| `psr/http-server-handler` | Request handler |
| `psr/http-server-middleware` | Middleware |
| `psr/http-factory` | Response factory |
| `solophp/contracts` (`^1.1`) | `WritableContainerInterface`, `EmitterInterface` |
| `solophp/router` (`^4.0`) | Route definitions, compiled matching, route cache |

The router contracts of `solophp/contracts` are not used: `Application` works with `Solo\Router\Router` directly.

## Next steps

- [Quick start](/guide/quick-start) — A small application from scratch
- [Lifecycle](/guide/lifecycle) — What happens at boot and on each request
- [Configuration](/guide/configuration) — `Config`, `routes.php`, providers and middleware
