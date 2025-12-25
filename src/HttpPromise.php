<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise;

use CurlHandle;
use CurlMultiHandle;
use InvalidArgumentException;
use Omegaalfa\HttpPromise\Exception\HttpException;
use Omegaalfa\HttpPromise\Http\HttpProcessorTrait;
use Omegaalfa\HttpPromise\Http\RequestOptions;
use Omegaalfa\HttpPromise\Promise\Deferred;
use Omegaalfa\HttpPromise\Promise\Promise;
use Omegaalfa\HttpPromise\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Asynchronous HTTP client with fluent API, using cURL multi handles and Promises.
 *
 * Example usage:
 * ```php
 * $http = HttpPromise::create($responseFactory)
 *     ->withBaseUrl('https://api.example.com')
 *     ->withBearerToken('your-token')
 *     ->asJson();
 *
 * // Async request
 * $promise = $http->get('/users')
 *     ->then(fn($response) => json_decode($response->getBody()->getContents()))
 *     ->catch(fn($error) => handleError($error));
 *
 * $http->wait(); // Process all pending requests
 *
 * // Or sync request
 * $response = $http->get('/users')->wait();
 * ```
 */
class HttpPromise
{
    use HttpProcessorTrait;

    /**
     * Valid HTTP methods.
     */
    private const array VALID_METHODS = [
        'GET', 'POST', 'PUT', 'PATCH', 'DELETE',
        'HEAD', 'OPTIONS', 'TRACE', 'CONNECT',
    ];

    /**
     * @var CurlMultiHandle
     */
    private CurlMultiHandle $multiHandle;

    /** @var array<int, array{handle: CurlHandle, deferred: Deferred<ResponseInterface>, url: string, method: string, attempt: int}> */
    private array $pendingHandles = [];

    /** @var list<array{deferred: Deferred<ResponseInterface>, url: string, method: string, headers: array<string, string|int|float|bool|null>, body: mixed, query: array<string, mixed>|null, attempt: int}> */
    private array $queuedHandles = [];

    /** @var list<CurlHandle> */
    private array $handlePool = [];

    /**
     * @var int
     */
    private int $maxPoolSize = 50;

    /** @var list<callable(array, callable): PromiseInterface> */
    private array $middlewares = [];

    /**
     * @var RequestOptions
     */
    private RequestOptions $options;

    // Concurrency control
    /**
     * @var int|mixed
     */
    private int $maxConcurrent = 50;

    // Performance metrics
    /**
     * @var int
     */
    private int $totalRequests = 0;
    /**
     * @var float
     */
    private float $totalTime = 0.0;
    /**
     * @var int
     */
    private int $successfulRequests = 0;
    /**
     * @var int
     */
    private int $failedRequests = 0;
    /**
     * @var float
     */
    private float $startTime = 0.0;

    /**
     * Creates a new HttpPromise client.
     *
     * @param ResponseInterface $responsePrototype Prototype response for cloning
     * @param RequestOptions|null $options Request options
     * @param int $maxConcurrent Maximum concurrent requests
     */
    public function __construct(
        protected ResponseInterface $responsePrototype,
        ?RequestOptions             $options = null,
        int                         $maxConcurrent = 50
    )
    {
        $this->multiHandle = curl_multi_init();
        $this->options = $options ?? RequestOptions::create();
        $this->maxConcurrent = max(1, $maxConcurrent);
        $this->startTime = microtime(true);

        $this->configureMultiHandle();
    }

    /**
     * Creates a new HttpPromise client (factory method).
     *
     * @param ResponseInterface $responsePrototype
     * @param RequestOptions|null $options
     * @param int $maxConcurrent
     * @return self
     */
    public static function create(ResponseInterface $responsePrototype, ?RequestOptions $options = null, int $maxConcurrent = 50): self
    {
        return new self($responsePrototype, $options, $maxConcurrent);
    }

    // =========================================================================
    // Fluent Configuration API
    // =========================================================================

    /**
     * @return void
     */
    private function configureMultiHandle(): void
    {
        // Enable HTTP/2 multiplexing for better concurrent performance
        if (defined('CURLMOPT_PIPELINING') && defined('CURLPIPE_MULTIPLEX')) {
            @curl_multi_setopt($this->multiHandle, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
        }

        // Set connection limits
        if (defined('CURLMOPT_MAX_TOTAL_CONNECTIONS')) {
            @curl_multi_setopt($this->multiHandle, CURLMOPT_MAX_TOTAL_CONNECTIONS, max(1, $this->maxConcurrent));
        }
        if (defined('CURLMOPT_MAX_HOST_CONNECTIONS')) {
            @curl_multi_setopt($this->multiHandle, CURLMOPT_MAX_HOST_CONNECTIONS, max(1, $this->maxConcurrent));
        }

        // Enable chunked transfer if available (faster for large responses)
        if (defined('CURLMOPT_CHUNK_LENGTH_PENALTY_SIZE')) {
            @curl_multi_setopt($this->multiHandle, CURLMOPT_CHUNK_LENGTH_PENALTY_SIZE, 1024 * 1024);
        }
    }

    /**
     * Returns a new instance with a base URL for all requests.
     *
     * @param string $baseUrl
     * @return $this
     */
    public function withBaseUrl(string $baseUrl): self
    {
        return $this->withOptions($this->options->withBaseUrl($baseUrl));
    }

    /**
     * Returns a new instance with different options.
     *
     * @param RequestOptions $options
     * @return $this
     */
    public function withOptions(RequestOptions $options): self
    {
        $clone = clone $this;
        $clone->options = $options;
        return $clone;
    }

    /**
     * Returns a new instance with Bearer token authentication.
     *
     * @param string $token
     * @return $this
     */
    public function withBearerToken(string $token): self
    {
        return $this->withOptions($this->options->withBearerToken($token));
    }

    /**
     * Returns a new instance with Basic authentication.
     *
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function withBasicAuth(string $username, string $password): self
    {
        return $this->withOptions($this->options->withBasicAuth($username, $password));
    }

    /**
     * Returns a new instance configured for form requests.
     *
     * @return $this
     */
    public function asForm(): self
    {
        return $this->withOptions($this->options->asForm());
    }

    /**
     * Returns a new instance with HTTP/2 enabled (if supported by libcurl).
     *
     * @param bool $enabled
     * @return $this
     */
    public function withHttp2(bool $enabled = true): self
    {
        return $this->withOptions($this->options->withHttp2($enabled));
    }

    /**
     * Returns a new instance with TCP keep-alive enabled (if supported by libcurl).
     *
     * @param bool $enabled
     * @return $this
     */
    public function withTcpKeepAlive(bool $enabled = true): self
    {
        return $this->withOptions($this->options->withTcpKeepAlive($enabled));
    }

    /**
     * Sets maximum number of idle CurlHandle instances kept in the pool.
     *
     * @param int $maxPoolSize
     * @return $this
     */
    public function withMaxPoolSize(int $maxPoolSize): self
    {
        $clone = clone $this;
        $clone->maxPoolSize = max(0, $maxPoolSize);
        if ($clone->maxPoolSize === 0) {
            // Disable pooling: close any currently pooled handles
            foreach ($clone->handlePool as $handle) {
                if ($handle instanceof CurlHandle) {
                    curl_close($handle);
                }
            }
            $clone->handlePool = [];
        } elseif (count($clone->handlePool) > $clone->maxPoolSize) {
            // Trim pool
            while (count($clone->handlePool) > $clone->maxPoolSize) {
                $handle = array_pop($clone->handlePool);
                if ($handle instanceof CurlHandle) {
                    curl_close($handle);
                }
            }
        }
        return $clone;
    }

    /**
     * Adds a middleware to the pipeline.
     * Middleware signature: function (array $request, callable $next): PromiseInterface
     *
     * @param callable $middleware
     * @return $this
     */
    public function withMiddleware(callable $middleware): self
    {
        $clone = clone $this;
        $clone->middlewares[] = $middleware;
        return $clone;
    }

    /**
     * Adds multiple middlewares to the pipeline.
     *
     * @param list<callable(array, callable): PromiseInterface> $middlewares
     */
    public function withMiddlewares(array $middlewares): self
    {
        $clone = clone $this;
        foreach ($middlewares as $middleware) {
            $clone->middlewares[] = $middleware;
        }
        return $clone;
    }

    /**
     * Returns a new instance with maximum concurrent requests limit.
     *
     * @param int $maxConcurrent
     * @return $this
     */
    public function withMaxConcurrent(int $maxConcurrent): self
    {
        $clone = clone $this;
        $clone->maxConcurrent = max(1, $maxConcurrent);
        $clone->configureMultiHandle();
        return $clone;
    }

    /**
     * Returns a new instance with additional headers.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return $this->withOptions($this->options->withHeaders($headers));
    }

    /**
     * Returns a new instance with timeout configuration.
     *
     * @param float $timeout
     * @return $this
     */
    public function withTimeout(float $timeout): self
    {
        return $this->withOptions($this->options->withTimeout($timeout));
    }

    /**
     * Returns a new instance with retry configuration.
     *
     * @param int $attempts Number of retry attempts
     * @param float $delay Delay between retries in seconds
     * @param array<int> $statusCodes HTTP status codes that should trigger a retry
     */
    public function withRetry(int $attempts, float $delay = 1.0, array $statusCodes = [429, 502, 503, 504]): self
    {
        return $this->withOptions($this->options->withRetry($attempts, $delay, $statusCodes));
    }

    /**
     * Returns a new instance with a proxy.
     *
     * @param string $proxy
     * @return $this
     */
    public function withProxy(string $proxy): self
    {
        return $this->withOptions($this->options->withProxy($proxy));
    }

    /**
     * Returns a new instance with SSL verification disabled.
     */
    public function withoutSSLVerification(): self
    {
        return $this->withOptions($this->options->withoutSSLVerification());
    }

    /**
     * Returns a new instance with a custom user agent.
     *
     * @param string $userAgent
     * @return $this
     */
    public function withUserAgent(string $userAgent): self
    {
        return $this->withOptions($this->options->withUserAgent($userAgent));
    }

    // =========================================================================
    // HTTP Request Methods (Async)
    // =========================================================================

    /**
     * Performs a GET request.
     *
     * @param string $url Request URL or path (relative to base URL)
     * @param array<string, string> $headers Request headers
     * @param array<string, mixed>|null $query Query parameters
     * @return PromiseInterface<ResponseInterface>
     */
    public function get(string $url, array $headers = [], ?array $query = null): PromiseInterface
    {
        return $this->request('GET', $url, $headers, null, $query);
    }

    /**
     * Performs a generic HTTP request.
     *
     * @param string $method HTTP method
     * @param string $url Request URL or path (relative to base URL)
     * @param array<string, string> $headers Request headers
     * @param mixed $body Request body
     * @param array<string, mixed>|null $query Query parameters
     * @return PromiseInterface<ResponseInterface>
     */
    public function request(
        string $method,
        string $url,
        array  $headers = [],
        mixed  $body = null,
        ?array $query = null,
        int    $attempt = 1
    ): PromiseInterface
    {
        $method = strtoupper($method);

        if (!$this->isValidMethod($method)) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP method: %s. Valid methods: %s', $method, implode(', ', self::VALID_METHODS))
            );
        }

        $request = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'query' => $query,
            'attempt' => $attempt,
        ];

        return $this->dispatchMiddlewares($request);
    }

    /**
     * Validates an HTTP method.
     *
     * @param string $method
     * @return bool
     */
    private function isValidMethod(string $method): bool
    {
        return in_array($method, self::VALID_METHODS, true);
    }

    /**
     * Dispatch request through middleware pipeline.
     *
     * @param array $request
     * @return PromiseInterface
     */
    private function dispatchMiddlewares(array $request): PromiseInterface
    {
        $core = function (array $req): PromiseInterface {
            return $this->sendRequestInternal($req);
        };

        $next = $core;
        for ($i = count($this->middlewares) - 1; $i >= 0; $i--) {
            $middleware = $this->middlewares[$i];
            $prevNext = $next;
            $next = static function (array $req) use ($middleware, $prevNext): PromiseInterface {
                return $middleware($req, $prevNext);
            };
        }

        return $next($request);
    }

    /**
     * @param array $request
     * @return PromiseInterface
     */
    private function sendRequestInternal(array $request): PromiseInterface
    {
        // Build full URL (applies base URL if configured)
        $fullUrl = $this->options->buildFullUrl($request['url']);
        $fullUrl = $this->buildUrl($fullUrl, $request['query']);

        // Merge headers with defaults
        $mergedHeaders = $this->mergeHeaders($request['headers'], $this->options->defaultHeaders);

        /** @var Deferred<ResponseInterface> $deferred */
        $deferred = new Deferred(fn() => $this->tick());

        // Increment metrics
        $this->totalRequests++;

        // Enqueue if no slots
        if (count($this->pendingHandles) >= $this->maxConcurrent) {
            $this->queuedHandles[] = [
                'deferred' => $deferred,
                'url' => $request['url'],
                'method' => $request['method'],
                'headers' => $mergedHeaders,
                'body' => $request['body'],
                'query' => $request['query'],
                'attempt' => $request['attempt'],
            ];

            return $deferred->promise();
        }

        $handle = $this->createHandle($request['method'], $fullUrl, $mergedHeaders, $request['body']);
        curl_multi_add_handle($this->multiHandle, $handle);

        $this->pendingHandles[spl_object_id($handle)] = [
            'handle' => $handle,
            'deferred' => $deferred,
            'url' => $request['url'],
            'method' => $request['method'],
            'attempt' => $request['attempt'],
        ];

        return $deferred->promise();
    }

    /**
     * Processes a single tick of the event loop.
     *
     * @return void
     */
    public function tick(): void
    {
        if (empty($this->pendingHandles) && empty($this->queuedHandles)) {
            return;
        }

        $this->processQueuedRequests();

        do {
            $status = curl_multi_exec($this->multiHandle, $stillRunning);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        $this->processCompletedHandles();
        $this->processQueuedRequests();
    }

    /**
     * Processes queued requests when slots become available.
     *
     * @return void
     */
    private function processQueuedRequests(): void
    {
        while (!empty($this->queuedHandles) && count($this->pendingHandles) < $this->maxConcurrent) {
            $request = array_shift($this->queuedHandles);
            if ($request === null) {
                return;
            }

            $fullUrl = $this->options->buildFullUrl($request['url']);
            $fullUrl = $this->buildUrl($fullUrl, $request['query']);

            $handle = $this->createHandle($request['method'], $fullUrl, $request['headers'], $request['body']);

            curl_multi_add_handle($this->multiHandle, $handle);

            $this->pendingHandles[spl_object_id($handle)] = [
                'handle' => $handle,
                'deferred' => $request['deferred'],
                'url' => $request['url'],
                'method' => $request['method'],
                'attempt' => $request['attempt'],
            ];
        }
    }

    /**
     * Creates a cURL handle for the request.
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array<string, string|int|float|bool|null> $headers Request headers
     * @param mixed $body Request body
     */
    private function createHandle(string $method, string $url, array $headers, mixed $body): CurlHandle
    {
        $handle = $this->acquireHandle();

        if ($url === '') {
            throw new RuntimeException('URL cannot be empty');
        }

        $userAgent = $this->options->getUserAgent();

        // Basic options
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_USERAGENT => $userAgent,

            // Timeouts
            CURLOPT_CONNECTTIMEOUT_MS => (int)($this->options->timeout * 1000),
            CURLOPT_TIMEOUT_MS => (int)($this->options->readTimeout * 1000),

            // Redirects
            CURLOPT_FOLLOWLOCATION => $this->options->followRedirects,
            CURLOPT_MAXREDIRS => $this->options->maxRedirects,

            // SSL
            CURLOPT_SSL_VERIFYPEER => $this->options->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->options->verifySSL ? 2 : 0,

            // Performance optimizations
            CURLOPT_TCP_NODELAY => true, // Disable Nagle's algorithm for lower latency
        ];

        // Advanced connection reuse / keep-alive knobs (best-effort)
        if (defined('CURLOPT_FORBID_REUSE')) {
            $opts[CURLOPT_FORBID_REUSE] = false;
        }
        if (defined('CURLOPT_FRESH_CONNECT')) {
            $opts[CURLOPT_FRESH_CONNECT] = false;
        }
        if ($this->options->tcpKeepAlive) {
            if (defined('CURLOPT_TCP_KEEPALIVE')) {
                $opts[CURLOPT_TCP_KEEPALIVE] = 1;
            }
            if (defined('CURLOPT_TCP_KEEPIDLE')) {
                $opts[CURLOPT_TCP_KEEPIDLE] = 30;
            }
            if (defined('CURLOPT_TCP_KEEPINTVL')) {
                $opts[CURLOPT_TCP_KEEPINTVL] = 15;
            }
        }

        // HTTP/2 support (best-effort)
        if ($this->options->http2) {
            if (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_2_0')) {
                $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
            } elseif (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_2TLS')) {
                $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
            }
            if (defined('CURLOPT_PIPEWAIT')) {
                $opts[CURLOPT_PIPEWAIT] = 1;
            }
        }

        curl_setopt_array($handle, $opts);

        // Headers (always set to avoid stale headers when reusing handles)
        curl_setopt($handle, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));

        // Body
        if ($body !== null && !in_array($method, ['GET', 'HEAD'], true)) {
            $processedBody = $this->formatParams($body, $headers);
            if ($processedBody !== null) {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $processedBody);
            }
        }

        // Proxy
        if ($this->options->proxy !== null) {
            curl_setopt($handle, CURLOPT_PROXY, $this->options->proxy);
        }

        return $handle;
    }

    /**
     * @return CurlHandle
     */
    private function acquireHandle(): CurlHandle
    {
        $handle = array_pop($this->handlePool);
        if ($handle instanceof CurlHandle) {
            if (function_exists('curl_reset')) {
                curl_reset($handle);
            }
            return $handle;
        }

        $newHandle = curl_init();
        if (!$newHandle instanceof CurlHandle) {
            throw new RuntimeException('Failed to create cURL handle');
        }
        return $newHandle;
    }

    /**
     * Processes completed cURL handles.
     */
    private function processCompletedHandles(): void
    {
        while ($info = curl_multi_info_read($this->multiHandle)) {
            if ($info['msg'] !== CURLMSG_DONE) {
                continue;
            }

            $handle = $info['handle'];

            if (!$handle instanceof CurlHandle) {
                continue;
            }

            $handleId = spl_object_id($handle);

            if (!isset($this->pendingHandles[$handleId])) {
                continue;
            }

            $data = $this->pendingHandles[$handleId];
            $deferred = $data['deferred'];

            try {
                if ($info['result'] === CURLE_OK) {
                    $response = $this->createResponse($handle);

                    // Check if we should retry based on status code
                    if ($this->shouldRetry($response, $data)) {
                        $this->scheduleRetry($data);
                    } else {
                        $deferred->resolve($response);
                        $this->successfulRequests++;
                    }
                } else {
                    $error = curl_error($handle);
                    $exception = HttpException::fromCurlError($error, $data['url'], $data['method']);

                    // Check if we should retry on network error
                    if ($data['attempt'] <= $this->options->retryAttempts) {
                        $this->scheduleRetry($data);
                    } else {
                        $deferred->reject($exception);
                        $this->failedRequests++;
                    }
                }
            } finally {
                curl_multi_remove_handle($this->multiHandle, $handle);
                $this->releaseHandle($handle);
                unset($this->pendingHandles[$handleId]);
            }
        }
    }

    /**
     * Creates a response from a completed cURL handle.
     */
    private function createResponse(CurlHandle $handle): ResponseInterface
    {
        $response = clone $this->responsePrototype;

        $statusCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $response = $response->withStatus($statusCode);

        $content = curl_multi_getcontent($handle);
        if ($content !== null && $content !== '') {
            $response->getBody()->write($content);
            $response->getBody()->rewind();
        }

        return $response;
    }

    // =========================================================================
    // Event Loop & State Management
    // =========================================================================

    /**
     * Checks if the request should be retried.
     *
     * @param ResponseInterface $response
     * @param array{handle: CurlHandle, deferred: Deferred<ResponseInterface>, url: string, method: string, attempt: int} $data
     * @return bool
     */
    private function shouldRetry(ResponseInterface $response, array $data): bool
    {
        if ($data['attempt'] > $this->options->retryAttempts) {
            return false;
        }

        return in_array($response->getStatusCode(), $this->options->retryStatusCodes, true);
    }

    /**
     * Schedules a retry for a failed request.
     *
     * @param array{handle: CurlHandle, deferred: Deferred<ResponseInterface>, url: string, method: string, attempt: int} $data
     */
    private function scheduleRetry(array $data): void
    {
        // Delay before retry
        usleep((int)($this->options->retryDelay * 1_000_000));

        // Re-queue the request
        $this->request(
            method: $data['method'],
            url: $data['url'],
            attempt: $data['attempt'] + 1
        );
    }

    /**
     * @param CurlHandle $handle
     * @return void
     */
    private function releaseHandle(CurlHandle $handle): void
    {
        if (count($this->handlePool) >= $this->maxPoolSize) {
            curl_close($handle);
            return;
        }

        $this->handlePool[] = $handle;
    }

    /**
     * Performs a POST request.
     *
     * @param string $url Request URL or path
     * @param mixed $body Request body
     * @param array<string, string> $headers Request headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function post(string $url, mixed $body = null, array $headers = []): PromiseInterface
    {
        return $this->request('POST', $url, $headers, $body);
    }

    /**
     * Performs a PUT request.
     *
     * @param string $url Request URL or path
     * @param mixed $body Request body
     * @param array<string, string> $headers Request headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function put(string $url, mixed $body = null, array $headers = []): PromiseInterface
    {
        return $this->request('PUT', $url, $headers, $body);
    }

    /**
     * Performs a PATCH request.
     *
     * @param string $url Request URL or path
     * @param mixed $body Request body
     * @param array<string, string> $headers Request headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function patch(string $url, mixed $body = null, array $headers = []): PromiseInterface
    {
        return $this->request('PATCH', $url, $headers, $body);
    }

    /**
     * Performs a DELETE request.
     *
     * @param string $url Request URL or path
     * @param mixed $body Request body
     * @param array<string, string> $headers Request headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function delete(string $url, mixed $body = null, array $headers = []): PromiseInterface
    {
        return $this->request('DELETE', $url, $headers, $body);
    }

    /**
     * Performs a HEAD request.
     *
     * @param string $url Request URL or path
     * @param array<string, string> $headers Request headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function head(string $url, array $headers = []): PromiseInterface
    {
        return $this->request('HEAD', $url, $headers);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Performs an OPTIONS request.
     *
     * @param string $url Request URL or path
     * @param array<string, string> $headers Request headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function options(string $url, array $headers = []): PromiseInterface
    {
        return $this->request('OPTIONS', $url, $headers);
    }

    /**
     * Sends a JSON request.
     *
     * @param string $method HTTP method
     * @param string $url Request URL or path
     * @param mixed $data Data to encode as JSON
     * @param array<string, string> $headers Additional headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function json(string $method, string $url, mixed $data, array $headers = []): PromiseInterface
    {
        return $this->asJson()->request($method, $url, $headers, $data);
    }

    /**
     * Returns a new instance configured for JSON requests.
     *
     * @return $this
     */
    public function asJson(): self
    {
        return $this->withOptions($this->options->asJson());
    }

    /**
     * Sends multiple requests concurrently.
     *
     * @param array<string, array{method: string, url: string, headers?: array<string, string>, body?: mixed}> $requests
     * @return PromiseInterface<array<string, ResponseInterface>>
     */
    public function concurrent(array $requests): PromiseInterface
    {

        $promises = array_map(function ($request) {
            return $this->request(
                method: $request['method'],
                url: $request['url'],
                headers: $request['headers'] ?? [],
                body: $request['body'] ?? null,
            );
        }, $requests);

        // Use Promise::all with a waitFn that ticks this client
        return Promise::all($promises);
    }

    /**
     * Races multiple requests, returning the first to complete.
     *
     * @param array<string, array{method: string, url: string, headers?: array<string, string>, body?: mixed}> $requests
     * @return PromiseInterface<ResponseInterface>
     */
    public function race(array $requests): PromiseInterface
    {

        $promises = array_map(function ($request) {
            return $this->request(
                method: $request['method'],
                url: $request['url'],
                headers: $request['headers'] ?? [],
                body: $request['body'] ?? null,
            );
        }, $requests);

        // Use Promise::race with a waitFn that ticks this client
        return Promise::race($promises, fn() => $this->tick());
    }

    /**
     * Waits for all pending requests to complete.
     *
     * @param float|null $timeout Maximum time to wait in seconds
     */
    public function wait(?float $timeout = null): void
    {
        $startTime = microtime(true);

        while ($this->hasPending()) {
            $this->processQueuedRequests();

            do {
                $status = curl_multi_exec($this->multiHandle, $stillRunning);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            $this->processCompletedHandles();

            if ($timeout !== null && (microtime(true) - $startTime) >= $timeout) {
                break;
            }

            if ($stillRunning > 0) {
                if (function_exists('curl_multi_poll')) {
                    \curl_multi_poll($this->multiHandle, 0.001);
                } else {
                    curl_multi_select($this->multiHandle, 0.001);
                }
            }
        }
    }

    /**
     * Checks if there are pending requests.
     *
     * @return bool
     */
    public function hasPending(): bool
    {
        return !empty($this->pendingHandles) || !empty($this->queuedHandles);
    }

    /**
     * Returns performance metrics.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        $uptime = microtime(true) - $this->startTime;

        return [
            'total_requests' => $this->totalRequests,
            'successful_requests' => $this->successfulRequests,
            'failed_requests' => $this->failedRequests,
            'pending_requests' => $this->pendingCount(),
            'queued_requests' => $this->queuedCount(),
            'uptime_seconds' => $uptime,
            'requests_per_second' => $uptime > 0 ? $this->totalRequests / $uptime : 0,
            'success_rate' => $this->totalRequests > 0 ? ($this->successfulRequests / $this->totalRequests) * 100 : 0,
        ];
    }

    /**
     * Returns the number of pending requests.
     *
     * @return int
     */
    public function pendingCount(): int
    {
        return count($this->pendingHandles);
    }

    /**
     * Returns the number of queued requests.
     *
     * @return int
     */
    public function queuedCount(): int
    {
        return count($this->queuedHandles);
    }

    /**
     * Returns the current options.
     *
     * @return RequestOptions
     */
    public function getOptions(): RequestOptions
    {
        return $this->options;
    }

    /**
     * Cleanup resources.
     */
    public function __destruct()
    {
        // Cancel any pending handles
        foreach ($this->pendingHandles as $data) {
            curl_multi_remove_handle($this->multiHandle, $data['handle']);
            curl_close($data['handle']);
        }

        curl_multi_close($this->multiHandle);
    }
}
