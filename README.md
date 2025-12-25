# HttpPromise

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A modern, async HTTP client for PHP with Promise/A+ like support and fluent API.

> ⚡ **Performance Highlight**: Up to **246% faster** than Guzzle on concurrent requests while maintaining zero external dependencies!

## Features

- 🚀 **Async HTTP requests** with cURL multi-handle
- ⚡ **Promise/A+ like** implementation with `then`, `catch`, `finally`
- 🔗 **Fluent API** for configuration (base URL, auth, headers, etc.)
- 🔄 **Concurrent requests** with `concurrent()` and `race()`
- 🔁 **Automatic retry** with configurable attempts and delays
- � **Connection pooling** with cURL handle reuse (up to 50 connections)
- 🎭 **Middleware support** for request/response interception
- ⚡ **HTTP/2 multiplexing** with native cURL support
- 🎚️ **Concurrency control** with configurable max concurrent requests
- 📊 **Performance metrics** built-in (requests/sec, success rate, etc.)
- �📦 **Zero external dependencies** (only ext-curl and PSR-7)
- 🎯 **Full PSR-7** ResponseInterface compatibility

## Installation

```bash
composer require omegaalfa/http-promise
```

## Requirements

- PHP 8.4+
- ext-curl (with HTTP/2 support for multiplexing features)
- PSR-7 ResponseInterface implementation

**For HTTP/2 Support:**
- libcurl 7.43.0+ compiled with nghttp2
- OpenSSL 1.0.2+ or equivalent TLS library

Check HTTP/2 availability:
```bash
php -r "echo (curl_version()['features'] & CURL_VERSION_HTTP2) ? 'HTTP/2 supported' : 'HTTP/2 not available';"
```

## Quick Start

```php
use Omegaalfa\HttpPromise\HttpPromise;
use Nyholm\Psr7\Response;

// Create client with fluent configuration
$http = HttpPromise::create(new Response())
    ->withBaseUrl('https://api.example.com')
    ->withBearerToken('your-api-token')
    ->asJson();

// Async request
$promise = $http->get('/users')
    ->then(fn($response) => json_decode($response->getBody()->getContents(), true))
    ->catch(fn($e) => ['error' => $e->getMessage()]);

// Wait for result
$users = $promise->wait();
```

## Usage Examples

### Basic Requests

```php
$http = HttpPromise::create(new Response());

// GET request
$http->get('https://api.example.com/users')->wait();

// POST request
$http->post('https://api.example.com/users', ['name' => 'John'])->wait();

// PUT request
$http->put('https://api.example.com/users/1', ['name' => 'Jane'])->wait();

// DELETE request
$http->delete('https://api.example.com/users/1')->wait();
```

### Fluent Configuration

```php
$http = HttpPromise::create(new Response())
    ->withBaseUrl('https://api.example.com')
    ->withBearerToken('token')
    ->withTimeout(30.0)
    ->withRetry(3, 1.0)
    ->asJson();

// All requests inherit configuration
$http->get('/users');        // → https://api.example.com/users
$http->post('/users', $data); // With JSON content-type
```

### Authentication

```php
// Bearer Token
$http = HttpPromise::create(new Response())
    ->withBearerToken('your-token');

// Basic Auth
$http = HttpPromise::create(new Response())
    ->withBasicAuth('username', 'password');

// Custom Headers
$http = HttpPromise::create(new Response())
    ->withHeaders([
        'X-API-Key' => 'your-key',
        'X-Custom-Header' => 'value',
    ]);
```

### JSON Requests

```php
$http = HttpPromise::create(new Response())
    ->withBaseUrl('https://api.example.com')
    ->asJson();

// POST JSON
$promise = $http->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Or use json() method explicitly
$promise = $http->json('POST', '/users', $data);
```

### Concurrent Requests

```php
$http = HttpPromise::create(new Response())
    ->withBaseUrl('https://api.example.com');

// Execute multiple requests in parallel
$results = $http->concurrent([
    'users' => ['method' => 'GET', 'url' => '/users'],
    'posts' => ['method' => 'GET', 'url' => '/posts'],
    'comments' => ['method' => 'GET', 'url' => '/comments'],
])->wait();

// Access results by key
$users = $results['users'];
$posts = $results['posts'];
```

### Race Requests

```php
// Get first response (useful for redundant endpoints)
$response = $http->race([
    'server1' => ['method' => 'GET', 'url' => 'https://server1.example.com/data'],
    'server2' => ['method' => 'GET', 'url' => 'https://server2.example.com/data'],
])->wait();
```

### Promise Chaining

```php
$http->get('/users/1')
    ->then(function ($response) {
        $user = json_decode($response->getBody()->getContents(), true);
        return $user['name'];
    })
    ->then(function ($name) {
        return strtoupper($name);
    })
    ->catch(function ($error) {
        return 'Unknown User';
    })
    ->finally(function () {
        echo "Request completed\n";
    })
    ->wait();
```

### Retry Configuration

```php
$http = HttpPromise::create(new Response())
    ->withRetry(
        attempts: 3,
        delay: 1.0, // seconds
        statusCodes: [429, 502, 503, 504]
    );

// Will retry up to 3 times on rate limit or server errors
$response = $http->get('https://api.example.com/data')->wait();
```

### Advanced Options

```php
use Omegaalfa\HttpPromise\Http\RequestOptions;

$options = RequestOptions::create()
    ->withBaseUrl('https://api.example.com')
    ->withTimeout(60.0)
    ->withRetry(3, 2.0)
    ->withHeaders(['Accept-Language' => 'en-US'])
    ->asJson();

$http = HttpPromise::create(new Response(), $options);
```

### Proxy & SSL

```php
$http = HttpPromise::create(new Response())
    ->withProxy('http://proxy.example.com:8080')
    ->withoutSSLVerification(); // For development only!
```

### Connection Pooling

```php
// Configure connection pool size (default: 50)
$http = HttpPromise::create(new Response())
    ->withMaxPoolSize(100); // Reuse up to 100 connections

// Disable pooling (closes connections after use)
$http = $http->withMaxPoolSize(0);

// TCP keep-alive (enabled by default)
$http = $http->withTcpKeepAlive(true);
```

### HTTP/2 Support

```php
// Enable HTTP/2 (if supported by libcurl)
$http = HttpPromise::create(new Response())
    ->withHttp2(true);

// HTTP/2 provides:
// - Multiplexing (multiple requests over single connection)
// - Header compression
// - Server push support
```

### Middleware System

```php
// Add request/response middleware
$http = HttpPromise::create(new Response())
    ->withMiddleware(function (array $request, callable $next) {
        // Before request
        echo "Sending {$request['method']} {$request['url']}\n";
        
        // Call next middleware/handler
        $promise = $next($request);
        
        // After response
        return $promise->then(function ($response) {
            echo "Received status: {$response->getStatusCode()}\n";
            return $response;
        });
    });

// Add multiple middlewares
$http = $http->withMiddlewares([
    $loggingMiddleware,
    $authMiddleware,
    $retryMiddleware,
]);
```

### Concurrency Control

```php
// Limit concurrent requests (default: 50)
$http = HttpPromise::create(new Response())
    ->withMaxConcurrent(10); // Only 10 requests in parallel

// Requests beyond limit are queued automatically
$promises = [];
for ($i = 0; $i < 100; $i++) {
    $promises[] = $http->get("/item/{$i}"); // Only 10 at a time
}

// Wait for all to complete
$http->wait();
```

### Performance Metrics

```php
$http = HttpPromise::create(new Response());

// Make some requests
$http->get('/users')->wait();
$http->post('/data', $payload)->wait();

// Get performance metrics
$metrics = $http->getMetrics();

/*
[
    'total_requests' => 2,
    'successful_requests' => 2,
    'failed_requests' => 0,
    'pending_requests' => 0,
    'queued_requests' => 0,
    'uptime_seconds' => 1.234,
    'requests_per_second' => 1.62,
    'success_rate' => 100.0,
]
*/
```

## Promise Static Methods

```php
use Omegaalfa\HttpPromise\Promise\Promise;

// Wait for all promises
$results = Promise::all([$promise1, $promise2, $promise3])->wait();

// Race - first to complete
$first = Promise::race([$promise1, $promise2])->wait();

// Any - first to succeed
$firstSuccess = Promise::any([$promise1, $promise2])->wait();

// All settled - wait for all, including failures
$outcomes = Promise::allSettled([$promise1, $promise2])->wait();

// Create resolved/rejected promises
$resolved = Promise::resolve($value);
$rejected = Promise::reject(new Exception('error'));

// Delay
$delayed = Promise::delay(1.0)->then(fn() => 'after 1 second');
```

## Performance

HttpPromise is designed for high-performance async HTTP operations:

- **Connection Pooling**: Reuses cURL handles to avoid overhead of creating new connections
- **HTTP/2 Multiplexing**: Multiple requests over single TCP connection (when supported)
- **Event Loop Optimization**: Adaptive select timeouts based on load
- **Zero-Copy**: Direct cURL multi-handle operations without intermediate buffers

### Benchmarks

Run the included benchmark suite:

```bash
php benchmark.php
```

**Results** (PHP 8.4.15, 2 iterations, httpbin.org):

| Benchmark | HttpPromise | Guzzle | Winner |
|-----------|-------------|--------|---------|
| Simple GET | 135.79 ms | 137.22 ms | **🏆 HttpPromise** (+1.1%) |
| POST JSON | 137.05 ms | 209.24 ms | **🏆 HttpPromise** (+52.7%) |
| **Concurrent x5** | **136.98 ms** | **474.07 ms** | **🏆 HttpPromise** (+246.1%) |
| Sequential x5 | 702.45 ms | 823.99 ms | **🏆 HttpPromise** (+17.3%) |
| GET + JSON Decode | 135.82 ms | 136.59 ms | **🏆 HttpPromise** (+0.6%) |
| **Concurrent x10** | **241.07 ms** | **629.95 ms** | **🏆 HttpPromise** (+161.3%) |
| Delayed (1s) | 1541.38 ms | 1154.07 ms | 🏆 Guzzle (-25.1%) |

**Overall**: HttpPromise wins **6/7 benchmarks** and is **15% faster overall** than Guzzle!

**Key Highlights**:
- ⚡ **Up to 246% faster** on concurrent requests (Concurrent x5)
- 🚀 **161% faster** on 10 concurrent requests  
- 💨 **52% faster** on POST JSON requests
- ♻️ Connection pooling makes dramatic difference in real-world scenarios
- 📦 Zero external dependencies while outperforming full-featured libraries

**Note**: Performance varies based on network conditions, server response times, and libcurl version.

## Best Practices

### 1. Reuse Client Instances
```php
// ✅ Good - reuses connections
$http = HttpPromise::create(new Response())
    ->withBaseUrl('https://api.example.com');

for ($i = 0; $i < 100; $i++) {
    $http->get("/item/{$i}");
}
$http->wait();

// ❌ Bad - creates new client each time
for ($i = 0; $i < 100; $i++) {
    HttpPromise::create(new Response())->get("/item/{$i}")->wait();
}
```

### 2. Use HTTP/2 for Same-Host Requests
```php
// ✅ Optimal for multiple requests to same domain
$http = HttpPromise::create(new Response())
    ->withHttp2(true) // Single connection, multiple streams
    ->withBaseUrl('https://api.example.com');
```

### 3. Configure Concurrency Limits
```php
// ✅ Prevent overwhelming servers or hitting rate limits
$http = HttpPromise::create(new Response())
    ->withMaxConcurrent(10); // Respects server capacity
```

### 4. Use Middleware for Cross-Cutting Concerns
```php
// ✅ DRY principle - logging, auth, retry logic in one place
$http = $http->withMiddlewares([
    $loggingMiddleware,
    $authRefreshMiddleware,
    $retryMiddleware,
]);
```

### 5. Monitor with Metrics
```php
// ✅ Track performance in production
$metrics = $http->getMetrics();
if ($metrics['success_rate'] < 95.0) {
    // Alert or adjust retry strategy
}
```

## Architecture

```
src/
├── HttpPromise.php           # Main HTTP client (event loop, pooling, middleware)
├── Http/
│   ├── RequestOptions.php    # Immutable configuration (HTTP/2, pooling, etc.)
│   └── HttpProcessorTrait.php # Header/body processing
├── Promise/
│   ├── PromiseInterface.php  # Promise contract
│   ├── Promise.php           # Promise/A+ implementation
│   └── Deferred.php          # Deferred pattern
└── Exception/
    ├── PromiseException.php  # Base exception
    ├── HttpException.php     # HTTP errors
    ├── TimeoutException.php  # Timeout errors
    └── RejectionException.php # Promise rejections
```

## API Reference

### HttpPromise Methods

| Method | Description |
|--------|-------------|
| `create(ResponseInterface, ?RequestOptions)` | Factory method |
| `withBaseUrl(string)` | Set base URL |
| `withBearerToken(string)` | Set Bearer auth |
| `withBasicAuth(string, string)` | Set Basic auth |
| `withHeaders(array)` | Add headers |
| `withTimeout(float)` | Set timeout |
| `withRetry(int, float, array)` | Configure retry |
| `withProxy(string)` | Set proxy |
| `withoutSSLVerification()` | Disable SSL verify |
| `withHttp2(bool)` | Enable HTTP/2 |
| `withTcpKeepAlive(bool)` | Enable TCP keep-alive |
| `withMaxPoolSize(int)` | Set connection pool size |
| `withMaxConcurrent(int)` | Set max concurrent requests |
| `withMiddleware(callable)` | Add middleware |
| `withMiddlewares(array)` | Add multiple middlewares |
| `asJson()` | Configure for JSON |
| `asForm()` | Configure for forms |
| `get(url, headers, query)` | GET request |
| `post(url, body, headers)` | POST request |
| `put(url, body, headers)` | PUT request |
| `patch(url, body, headers)` | PATCH request |
| `delete(url, body, headers)` | DELETE request |
| `concurrent(array)` | Parallel requests |
| `race(array)` | Race requests |
| `wait(?timeout)` | Wait for all pending |
| `tick()` | Process one event loop tick |
| `hasPending()` | Check if has pending requests |
| `pendingCount()` | Get pending request count |
| `queuedCount()` | Get queued request count |
| `getMetrics()` | Get performance metrics |

### PromiseInterface Methods

| Method | Description |
|--------|-------------|
| `then(?callable, ?callable)` | Chain handlers |
| `catch(callable)` | Handle rejection |
| `finally(callable)` | Always execute |
| `wait(?float)` | Wait for resolution |
| `getState()` | Get current state |
| `isPending()` | Check if pending |
| `isFulfilled()` | Check if fulfilled |
| `isRejected()` | Check if rejected |

## Troubleshooting

### Promise Timeout Error
```
TimeoutException: Promise could not be resolved
```

**Causes:**
- Promise not linked to HttpPromise event loop (missing `waitFn`)
- Network timeout shorter than request completion time

**Solutions:**
```php
// ✅ Increase timeout
$http = $http->withTimeout(60.0);

// ✅ Use HttpPromise methods directly (auto-wires event loop)
$promise = $http->concurrent($requests); // ✅ Has waitFn
```

### HTTP/2 Not Working

**Check support:**
```bash
php -r "var_dump(curl_version());"
```

Look for `HTTP2` in features bitmask.

**Enable in HttpPromise:**
```php
$http = $http->withHttp2(true);
```

### Connection Pool Not Reusing

**Ensure:**
1. Same HttpPromise instance across requests
2. Pool size > 0 (default: 50)
3. No manual curl handle closing

```php
// ✅ Correct
$http = HttpPromise::create(new Response());
for ($i = 0; $i < 100; $i++) {
    $http->get("/item/{$i}");
}

// ❌ Wrong - creates new instance each time
for ($i = 0; $i < 100; $i++) {
    HttpPromise::create(new Response())->get("/item/{$i}")->wait();
}
```

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Run `./vendor/bin/phpunit`
5. Submit a pull request

## License

MIT License - see [LICENSE](LICENSE) file.
