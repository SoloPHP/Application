<?php

declare(strict_types=1);

namespace Solo\Application\Tests;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Router\RouterInterface;

final class TestServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(RouterInterface::class, fn() => new class implements RouterInterface {
            public function addRoute(
                string $method,
                string $path,
                callable|array|string $handler,
                array $options = []
            ): void {
            }
            public function match(string $method, string $uri): array|false
            {
                return false;
            }
        });

        $container->set(ResponseFactoryInterface::class, fn() => new class implements ResponseFactoryInterface {
            public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
            {
                return new class implements ResponseInterface {
                    public function getStatusCode(): int
                    {
                        return 200;
                    }
                    public function withStatus(int $code, string $reasonPhrase = ''): static
                    {
                        return $this;
                    }
                    public function getReasonPhrase(): string
                    {
                        return '';
                    }
                    public function getProtocolVersion(): string
                    {
                        return '1.1';
                    }
                    public function withProtocolVersion(string $version): static
                    {
                        return $this;
                    }
                    public function getHeaders(): array
                    {
                        return [];
                    }
                    public function hasHeader(string $name): bool
                    {
                        return false;
                    }
                    public function getHeader(string $name): array
                    {
                        return [];
                    }
                    public function getHeaderLine(string $name): string
                    {
                        return '';
                    }
                    public function withHeader(string $name, $value): static
                    {
                        return $this;
                    }
                    public function withAddedHeader(string $name, $value): static
                    {
                        return $this;
                    }
                    public function withoutHeader(string $name): static
                    {
                        return $this;
                    }
                    public function getBody(): StreamInterface
                    {
                        return new class implements StreamInterface {
                            public function __toString(): string
                            {
                                return '';
                            }
                            public function close(): void
                            {
                            }
                            public function detach()
                            {
                                return null;
                            }
                            public function getSize(): ?int
                            {
                                return 0;
                            }
                            public function tell(): int
                            {
                                return 0;
                            }
                            public function eof(): bool
                            {
                                return true;
                            }
                            public function isSeekable(): bool
                            {
                                return false;
                            }
                            public function seek(int $offset, int $whence = SEEK_SET): void
                            {
                            }
                            public function rewind(): void
                            {
                            }
                            public function isWritable(): bool
                            {
                                return false;
                            }
                            public function write(string $string): int
                            {
                                return 0;
                            }
                            public function isReadable(): bool
                            {
                                return false;
                            }
                            public function read(int $length): string
                            {
                                return '';
                            }
                            public function getContents(): string
                            {
                                return '';
                            }
                            public function getMetadata(?string $key = null)
                            {
                                return null;
                            }
                        };
                    }
                    public function withBody(StreamInterface $body): static
                    {
                        return $this;
                    }
                };
            }
        });
    }
}
