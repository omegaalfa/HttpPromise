<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\HttpPromise\HttpPromise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Simple PSR-7 Response implementation for testing.
 */
class SimpleResponse implements ResponseInterface
{
    private int $statusCode = 200;
    private string $reasonPhrase = 'OK';
    /** @var array<string, array<string>> */
    private array $headers = [];
    private \Psr\Http\Message\StreamInterface $body;
    private string $protocolVersion = '1.1';

    public function __construct()
    {
        $this->body = new SimpleStream();
    }

    public function getStatusCode(): int { return $this->statusCode; }
    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase ?: match ($code) {
            200 => 'OK', 201 => 'Created', 204 => 'No Content',
            400 => 'Bad Request', 401 => 'Unauthorized', 404 => 'Not Found',
            500 => 'Internal Server Error', default => '',
        };
        return $clone;
    }
    public function getReasonPhrase(): string { return $this->reasonPhrase; }
    public function getProtocolVersion(): string { return $this->protocolVersion; }
    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this; $clone->protocolVersion = $version; return $clone;
    }
    public function getHeaders(): array { return $this->headers; }
    public function hasHeader(string $name): bool { return isset($this->headers[strtolower($name)]); }
    public function getHeader(string $name): array { return $this->headers[strtolower($name)] ?? []; }
    public function getHeaderLine(string $name): string { return implode(', ', $this->getHeader($name)); }
    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        return $clone;
    }
    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $existing = $clone->headers[strtolower($name)] ?? [];
        $clone->headers[strtolower($name)] = array_merge($existing, is_array($value) ? $value : [$value]);
        return $clone;
    }
    public function withoutHeader(string $name): static
    {
        $clone = clone $this; unset($clone->headers[strtolower($name)]); return $clone;
    }
    public function getBody(): \Psr\Http\Message\StreamInterface { return $this->body; }
    public function withBody(\Psr\Http\Message\StreamInterface $body): static
    {
        $clone = clone $this; $clone->body = $body; return $clone;
    }
    public function __clone() { $this->body = new SimpleStream(); }
}

class SimpleStream implements \Psr\Http\Message\StreamInterface
{
    private string $content = '';
    private int $position = 0;

    public function __toString(): string { return $this->content; }
    public function close(): void { $this->content = ''; $this->position = 0; }
    public function detach() { return null; }
    public function getSize(): ?int { return strlen($this->content); }
    public function tell(): int { return $this->position; }
    public function eof(): bool { return $this->position >= strlen($this->content); }
    public function isSeekable(): bool { return true; }
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->position = match ($whence) {
            SEEK_SET => $offset, SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen($this->content) + $offset, default => $offset,
        };
    }
    public function rewind(): void { $this->position = 0; }
    public function isWritable(): bool { return true; }
    public function write(string $string): int { $this->content .= $string; return strlen($string); }
    public function isReadable(): bool { return true; }
    public function read(int $length): string
    {
        $result = substr($this->content, $this->position, $length);
        $this->position += strlen($result);
        return $result;
    }
    public function getContents(): string { return substr($this->content, $this->position); }
    public function getMetadata(?string $key = null): mixed { return $key === null ? [] : null; }
}