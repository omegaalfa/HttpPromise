<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Http;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Trait for processing HTTP headers and parameters.
 */
trait HttpProcessorTrait
{
    /**
     * Formats headers array into cURL format.
     *
     * @param array<string, string|int|float|bool|null> $headers Associative array of headers
     * @return array<int, string> Formatted headers for cURL
     * @throws InvalidArgumentException If header name or value is invalid
     */
    protected function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            
            // Validar tipo do valor
            if (!is_scalar($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Header value must be scalar, %s given for "%s"',
                    get_debug_type($value),
                    $name
                ));
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            
            // Sanitizar nome e valor (CRLF injection protection)
            $name = $this->sanitizeHeaderName((string)$name);
            $value = $this->sanitizeHeaderValue((string)$value);

            $formatted[] = sprintf('%s: %s', $name, $value);
        }

        return $formatted;
    }
    
    /**
     * Sanitizes header name to prevent injection attacks.
     *
     * @param string $name
     * @return string
     * @throws InvalidArgumentException
     */
    private function sanitizeHeaderName(string $name): string
    {
        // RFC 7230: header name = token
        if (!preg_match('/^[!#$%&\'*+\-.0-9A-Z^_`a-z|~]+$/', $name)) {
            throw new InvalidArgumentException("Invalid header name: {$name}");
        }
        return $name;
    }
    
    /**
     * Sanitizes header value to prevent CRLF injection.
     *
     * @param string $value
     * @return string
     * @throws InvalidArgumentException
     */
    private function sanitizeHeaderValue(string $value): string
    {
        // Remover CRLF e caracteres de controle
        $value = str_replace(["\r", "\n", "\0"], '', $value);
        
        // RFC 7230: field-value
        if (!preg_match('/^[\x20-\x7E\x80-\xFF]*$/', $value)) {
            throw new InvalidArgumentException('Invalid header value: contains invalid characters');
        }
        
        return trim($value);
    }

    /**
     * Processes request parameters based on content type.
     *
     * @param mixed $params Request parameters
     * @param array<string, string|int|float|bool|null> $headers Request headers
     * @return string|null Processed parameters
     * @throws RuntimeException If JSON encoding fails
     */
    protected function formatParams(mixed $params, array $headers): ?string
    {
        if ($params === null) {
            return null;
        }

        if (is_string($params)) {
            return $params;
        }

        $contentType = $this->getContentType($headers);

        // JSON content type
        if (str_contains($contentType, 'json')) {
            return $this->encodeJson($params);
        }

        // Form data
        if (is_array($params) || is_object($params)) {
            return http_build_query((array)$params);
        }

        return is_scalar($params) ? (string)$params : null;
    }

    /**
     * Gets the content type from headers.
     *
     * @param array<string, string|int|float|bool|null> $headers
     * @return string
     */
    protected function getContentType(array $headers): string
    {
        // Case-insensitive header lookup
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                return strtolower((string)$value);
            }
        }

        return 'application/x-www-form-urlencoded';
    }

    /**
     * Encodes data as JSON.
     *
     * @param mixed $data Data to encode
     * @return string JSON string
     * @throws RuntimeException If encoding fails
     */
    protected function encodeJson(mixed $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new RuntimeException('JSON encoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decodes JSON string.
     *
     * @param string $json JSON string
     * @param bool $associative Return associative array
     * @return mixed Decoded data
     * @throws RuntimeException If decoding fails
     */
    protected function decodeJson(string $json, bool $associative = true): mixed
    {
        try {
            return json_decode($json, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('JSON decoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Merges default headers with custom headers.
     *
     * @param array<string, string|int|float|bool|null> $custom Custom headers
     * @param array<string, string|int|float|bool|null> $defaults Default headers
     * @return array<string, string|int|float|bool|null>
     */
    protected function mergeHeaders(array $custom, array $defaults = []): array
    {
        // Normalize header names to lowercase for comparison
        $normalized = [];
        foreach ($defaults as $name => $value) {
            $normalized[strtolower($name)] = [$name, $value];
        }

        foreach ($custom as $name => $value) {
            $normalized[strtolower($name)] = [$name, $value];
        }

        $result = [];
        foreach ($normalized as [$name, $value]) {
            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * Builds a URL with query parameters.
     *
     * @param string $url Base URL
     * @param array<string, mixed>|null $params Query parameters
     * @return string Full URL
     */
    protected function buildUrl(string $url, ?array $params): string
    {
        if (empty($params)) {
            return $url;
        }

        $query = http_build_query($params);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . $query;
    }

    /**
     * Parses a URL and returns its components.
     *
     * @param string $url
     * @return array{scheme?: string, host?: string, port?: int, path?: string, query?: string}
     */
    protected function parseUrl(string $url): array
    {
        $parsed = parse_url($url);
        return $parsed === false ? [] : $parsed;
    }
}
