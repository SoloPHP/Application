<?php

declare(strict_types=1);

namespace Solo\Application;

use Psr\Container\ContainerInterface;

/**
 * The one rule by which route handlers and middleware are turned into something to call.
 *
 * @internal
 */
final class CallableResolver
{
    /**
     * A string is a container id, unless the container does not have it and it is callable,
     * e.g. a function name or "Class::staticMethod". [class, method] calls the method on an
     * instance from the container, unless the method is static. Anything else is returned as it is.
     */
    public static function resolve(ContainerInterface $container, mixed $target): mixed
    {
        if (is_string($target)) {
            return !$container->has($target) && is_callable($target) ? $target : $container->get($target);
        }

        if (
            is_array($target) && array_is_list($target) && count($target) === 2
            && is_string($target[0]) && is_string($target[1]) && !is_callable($target)
        ) {
            return [$container->get($target[0]), $target[1]];
        }

        return $target;
    }

    /**
     * "Class::method" for anything shaped like a callable, the type otherwise. Objects are named
     * by get_debug_type(): PHP's own callable names for closures differ between versions, and for
     * anonymous classes they contain a NUL byte and the file path.
     */
    public static function describe(mixed $target): string
    {
        if (is_array($target) && is_object($target[0] ?? null)) {
            $target[0] = get_debug_type($target[0]);
        }

        return !is_object($target) && is_callable($target, true, $name) ? $name : get_debug_type($target);
    }
}
