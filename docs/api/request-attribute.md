# RequestAttribute

`Solo\Application\RequestAttribute` names the request attributes that `RoutingMiddleware` writes and `RouteDispatcher` reads.

```php
final class RequestAttribute
```

| Constant | Value | Type | Description |
|----------|-------|------|-------------|
| `HANDLER` | `'handler'` | `array{class-string,string} \| string \| callable` | Matched handler: `[class, method]`, a container ID or a callable |
| `PARAMS` | `'params'` | `array<string, string>` | Route parameters, percent-decoded |
| `ROUTE_NAME` | `'route_name'` | `string \| null` | Route name, or `null` for an unnamed route |
| `ROUTE_ATTRIBUTES` | `'route_attributes'` | `array<string, mixed>` | Custom route attributes |

```php
use Solo\Application\RequestAttribute;

$name = $request->getAttribute(RequestAttribute::ROUTE_NAME);
$permission = $request->getAttribute(RequestAttribute::ROUTE_ATTRIBUTES, [])['permission'] ?? null;
```

A [custom routing middleware](/features/routing#custom-routing-middleware) sets `HANDLER` and `PARAMS` for the dispatcher.
