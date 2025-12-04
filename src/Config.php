<?php

declare(strict_types=1);

namespace Solo\Application;

final readonly class Config
{
    public function __construct(
        public string $basePath,
        public string $routesPath,
        public array $providers,
        public array $middleware,
    ) {
    }
}
