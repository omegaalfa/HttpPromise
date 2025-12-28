<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Http;

/**
 * Immutable configuration options for HTTP requests.
 *
 * Uses fluent API with immutable objects (each method returns a new instance).
 */
final class RequestOptions
{
    /**
     * @param string $baseUrl Base URL for all requests
     * @param float $timeout Connection timeout in seconds
     * @param float $readTimeout Read timeout in seconds
     * @param bool $followRedirects Whether to follow redirects
     * @param int $maxRedirects Maximum number of redirects to follow
     * @param bool $verifySSL Whether to verify SSL certificates
     * @param string|null $userAgent Custom user agent string
     * @param string|null $proxy Proxy URL
     * @param array<string, string> $defaultHeaders Default headers for all requests
     * @param int $retryAttempts Number of retry attempts on failure
     * @param float $retryDelay Delay between retries in seconds
     * @param array<int> $retryStatusCodes HTTP status codes that should trigger a retry
     */
    public function __construct(
        public readonly string  $baseUrl = '',
        public readonly float   $timeout = 30.0,
        public readonly float   $readTimeout = 30.0,
        public readonly bool    $followRedirects = true,
        public readonly int     $maxRedirects = 5,
        public readonly bool    $verifySSL = true,
        public readonly ?string $userAgent = null,
        public readonly ?string $proxy = null,
        public readonly array   $defaultHeaders = [],
        public readonly int     $retryAttempts = 0,
        public readonly float   $retryDelay = 1.0,
        public readonly array   $retryStatusCodes = [429, 502, 503, 504],
        public readonly bool    $http2 = false,
        public readonly bool    $tcpKeepAlive = true,
    )
    {
    }

    /**
     * Creates default options.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Enables or disables HTTP/2 (when supported by the installed libcurl).
     */
    public function withHttp2(bool $enabled = true): self
    {
        return $this->with(['http2' => $enabled]);
    }

    /**
     * Creates a new instance with modified values.
     *
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(
            baseUrl: $changes['baseUrl'] ?? $this->baseUrl,
            timeout: $changes['timeout'] ?? $this->timeout,
            readTimeout: $changes['readTimeout'] ?? $this->readTimeout,
            followRedirects: $changes['followRedirects'] ?? $this->followRedirects,
            maxRedirects: $changes['maxRedirects'] ?? $this->maxRedirects,
            verifySSL: $changes['verifySSL'] ?? $this->verifySSL,
            userAgent: $changes['userAgent'] ?? $this->userAgent,
            proxy: $changes['proxy'] ?? $this->proxy,
            defaultHeaders: $changes['defaultHeaders'] ?? $this->defaultHeaders,
            retryAttempts: $changes['retryAttempts'] ?? $this->retryAttempts,
            retryDelay: $changes['retryDelay'] ?? $this->retryDelay,
            retryStatusCodes: $changes['retryStatusCodes'] ?? $this->retryStatusCodes,
            http2: $changes['http2'] ?? $this->http2,
            tcpKeepAlive: $changes['tcpKeepAlive'] ?? $this->tcpKeepAlive,
        );
    }

    /**
     * Enables or disables TCP keep-alive (when supported by the installed libcurl).
     */
    public function withTcpKeepAlive(bool $enabled = true): self
    {
        return $this->with(['tcpKeepAlive' => $enabled]);
    }

    /**
     * Returns options with base URL.
     */
    public function withBaseUrl(string $baseUrl): self
    {
        return $this->with(['baseUrl' => rtrim($baseUrl, '/')]);
    }

    /**
     * Returns options with modified timeout.
     */
    public function withTimeout(float $timeout): self
    {
        return $this->with(['timeout' => $timeout]);
    }

    /**
     * Returns options with modified read timeout.
     */
    public function withReadTimeout(float $readTimeout): self
    {
        return $this->with(['readTimeout' => $readTimeout]);
    }

    /**
     * Returns options with retry configuration.
     *
     * @param int $attempts Number of retry attempts
     * @param float $delay Delay between retries in seconds
     * @param array<int> $statusCodes HTTP status codes that should trigger a retry
     */
    public function withRetry(int $attempts, float $delay = 1.0, array $statusCodes = [429, 502, 503, 504]): self
    {
        return $this->with([
            'retryAttempts' => $attempts,
            'retryDelay' => $delay,
            'retryStatusCodes' => $statusCodes,
        ]);
    }

    /**
     * Returns options with Bearer token authentication.
     */
    public function withBearerToken(string $token): self
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
    }

    /**
     * Returns options with custom headers added.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return $this->with([
            'defaultHeaders' => array_merge($this->defaultHeaders, $headers),
        ]);
    }

    /**
     * Returns options with Basic authentication.
     */
    public function withBasicAuth(string $username, string $password): self
    {
        $token = base64_encode($username . ':' . $password);
        return $this->withHeaders(['Authorization' => 'Basic ' . $token]);
    }

    /**
     * Returns options configured for JSON requests.
     */
    public function asJson(): self
    {
        return $this->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Returns options configured for form requests.
     */
    public function asForm(): self
    {
        return $this->withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
    }

    /**
     * Returns options with proxy.
     */
    public function withProxy(string $proxy): self
    {
        return $this->with(['proxy' => $proxy]);
    }

    /**
     * Returns options with SSL verification disabled.
     * 
     * WARNING: This disables TLS certificate verification and makes connections
     * vulnerable to man-in-the-middle attacks. ONLY use in development/testing.
     * NEVER use in production environments.
     */
    public function withoutSSLVerification(): self
    {
        // Log critical security warning (exceto durante testes)
        if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists(\PHPUnit\Framework\TestCase::class, false)) {
            error_log('[SECURITY WARNING] SSL verification disabled - vulnerable to MITM attacks. Only use in development!');
        }
        
        return $this->with(['verifySSL' => false]);
    }

    /**
     * Returns options with custom user agent.
     */
    public function withUserAgent(string $userAgent): self
    {
        return $this->with(['userAgent' => $userAgent]);
    }

    /**
     * Returns options with redirects disabled.
     */
    public function withoutRedirects(): self
    {
        return $this->with(['followRedirects' => false]);
    }

    /**
     * Returns options with max redirects.
     */
    public function withMaxRedirects(int $maxRedirects): self
    {
        return $this->with(['maxRedirects' => $maxRedirects]);
    }

    /**
     * Gets the default user agent string.
     */
    public function getUserAgent(): string
    {
        return $this->userAgent ?? 'HttpPromise/1.0 PHP/' . PHP_VERSION;
    }

    /**
     * Builds the full URL from base URL and path.
     */
    public function buildFullUrl(string $path): string
    {
        if ($this->baseUrl === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
