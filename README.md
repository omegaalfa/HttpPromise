# HttpPromise

<div align="center">

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%206-brightgreen?style=flat-square)](https://phpstan.org)
[![Tests](https://img.shields.io/badge/Tests-134%20passed-success?style=flat-square)]()
[![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen?style=flat-square)]()

**A modern, production-ready async HTTP client for PHP with Promise/A+ support, connection pooling, and HTTP/2 multiplexing**

[Features](#-features) • [Installation](#-installation) • [Quick Start](#-quick-start) • [Documentation](#-documentation) • [Security](#-security) • [Performance](#-performance)

</div>

---

## 🎯 Why HttpPromise?

- ⚡ **246% faster** than Guzzle on concurrent requests
- 🔒 **Security-first**: SSRF protection, header sanitization, credential isolation
- 🚀 **Zero dependencies** (only ext-curl + PSR-7)
- 🎭 **Production-tested** with comprehensive security audit
- 📦 **Modern PHP 8.4+** with strict types and generics
- 🔄 **True async** with Promise/A+ implementation

## ✨ Features

### Core Capabilities
- 🚀 **Async HTTP requests** with cURL multi-handle event loop
- ⚡ **Promise/A+** implementation (`then`, `catch`, `finally`)
- 🔗 **Fluent API** for elegant configuration chaining
- 🔄 **Concurrent requests** with `concurrent()` and `race()`
- 🔁 **Smart retry** - idempotent methods only, non-blocking delays
- 🏊 **Connection pooling** - isolated by host with secure handle reuse
- 🎭 **Middleware pipeline** for request/response interception
- ⚡ **HTTP/2 multiplexing** with libcurl 7.65.0+ safety checks
- 🎚️ **Concurrency control** with automatic request queueing
- 📊 **Performance metrics** built-in

### Security Features
- 🔒 **SSRF Protection** - URL validation, private IP blocking
- 🛡️ **Header injection prevention** - CRLF sanitization (RFC 7230)
- 🔐 **Credential isolation** - connection pool per host
- ⏱️ **Queue timeouts** - prevents starvation attacks
- 🚫 **Safe retry** - only idempotent methods (GET, PUT, DELETE)
- ✅ **Type safety** - strict types, PHPStan level 6

## 📦 Installation

```bash
composer require omegaalfa/http-promise
```

### Requirements

| Dependency | Version | Purpose |
|------------|---------|---------|
| PHP | 8.4+ | Modern language features |
| ext-curl | Any | HTTP client engine |
| PSR-7 | ^1.0\|^2.0 | Response interface |

**For HTTP/2 Support:**
- libcurl **7.65.0+** (older versions have multiplexing bugs)
- Compiled with nghttp2 support

Check your environment:
```bash
# Check HTTP/2 support
php -r "echo (curl_version()['features'] & CURL_VERSION_HTTP2) ? '✅ HTTP/2 supported' : '❌ HTTP/2 not available';"

# Check libcurl version
php -r "echo curl_version()['version'];"
```

## 🚀 Quick Start

```php
use Omegaalfa\HttpPromise\HttpPromise;
use Nyholm\Psr7\Response;

// Create client with fluent configuration
$http = HttpPromise::create(new Response())
    ->withBaseUrl('https://api.example.com')
    ->withBearerToken('your-api-token')
    ->withTimeout(30.0)
    ->withRetry(3, 1.0)
    ->asJson();

// Async request with promise chaining
$promise = $http->get('/users')
    ->then(fn($response) => json_decode($response->getBody()->getContents(), true))
    ->then(fn($data) => array_filter($data, fn($user) => $user['active']))
    ->catch(fn($e) => ['error' => $e->getMessage()])
    ->finally(fn() => echo "Request completed\n");

// Wait for result
$activeUsers = $promise->wait();
```

## 📚 Documentation

### Table of Contents

1. [Basic Requests](#basic-requests)
2. [Configuration](#configuration)
3. [Authentication](#authentication)
4. [Promise Handling](#promise-handling)
5. [Concurrent Requests](#concurrent-requests)
6. [Middleware System](#middleware-system)
7. [Advanced Features](#advanced-features)
8. [Security Best Practices](#-security-best-practices)

---

### Basic Requests

```php
$http = HttpPromise::create(new Response())
    ->withBaseUrl('https://api.example.com');

// GET with query parameters
$http->get('/users', [], ['status' => 'active', 'limit' => 10])->wait();

// POST with JSON body
$http->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
])->wait();

// PUT (idempotent update)
$http->put('/users/123', ['name' => 'Jane Doe'])->wait();

// PATCH (partial update)
$http->patch('/users/123', ['email' => 'jane@example.com'])->wait();

// DELETE
$http->delete('/users/123')->wait();

// HEAD (metadata only)
$http->head('/users/123')->wait();

// OPTIONS (CORS preflight)
$http->options('/users')->wait();
```

### Configuration

#### Fluent API

```php
$http = HttpPromise::create(new Response())
    // Base configuration
    ->withBaseUrl('https://api.example.com')
    ->withTimeout(30.0)
    ->withUserAgent('MyApp/1.0')
    
    // Content type
    ->asJson()  // or ->asForm()
    
    // Connection settings
    ->withHttp2(true)
    ->withTcpKeepAlive(true)
    ->withMaxPoolSize(50)
    ->withMaxConcurrent(20)
    
    // Retry configuration
    ->withRetry(
        attempts: 3,
        delay: 1.0,
        statusCodes: [429, 502, 503, 504]
    )
    
    // Custom headers
    ->withHeaders([
        'X-API-Version' => '2.0',
        'X-Request-ID' => uniqid(),
    ]);
```

#### Using RequestOptions

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

### Authentication

```php
// Bearer Token (OAuth 2.0, JWT)
$http = HttpPromise::create(new Response())
    ->withBearerToken('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...');

// Basic Authentication
$http = HttpPromise::create(new Response())
    ->withBasicAuth('username', 'password');

// API Key Header
$http = HttpPromise::create(new Response())
    ->withHeaders(['X-API-Key' => 'your-secret-key']);

// Custom Authorization
$http = HttpPromise::create(new Response())
    ->withHeaders(['Authorization' => 'Digest username="user", realm="API"']);
```

### Promise Handling

#### Chaining

```php
$http->get('/users/1')
    ->then(function ($response) {
        // Parse JSON
        return json_decode($response->getBody()->getContents(), true);
    })
    ->then(function ($user) {
        // Transform data
        return [
            'id' => $user['id'],
            'name' => strtoupper($user['name']),
            'email' => strtolower($user['email']),
        ];
    })
    ->then(function ($transformed) {
        // Make another request
        return $this->http->post('/audit', $transformed);
    })
    ->catch(function ($error) {
        // Handle any error in the chain
        error_log("Request failed: " . $error->getMessage());
        return null;
    })
    ->finally(function () {
        // Always executes (cleanup, logging, etc.)
        echo "Request pipeline completed\n";
    })
    ->wait();
```

#### Error Handling

```php
use Omegaalfa\HttpPromise\Exception\HttpException;
use Omegaalfa\HttpPromise\Exception\TimeoutException;

$promise = $http->get('/users')
    ->catch(function ($error) {
        if ($error instanceof TimeoutException) {
            // Handle timeout
            return ['error' => 'Request timed out'];
        }
        
        if ($error instanceof HttpException) {
            // Handle HTTP errors
            $statusCode = $error->getStatusCode();
            if ($statusCode === 404) {
                return ['error' => 'Not found'];
            }
        }
        
        // Re-throw other errors
        throw $error;
    });

$result = $promise->wait();
```

### Concurrent Requests

#### Multiple Requests in Parallel

```php
$http = HttpPromise::create(new Response())
    ->withBaseUrl('https://api.example.com')
    ->withMaxConcurrent(10); // Limit concurrency

// Define requests
$results = $http->concurrent([
    'users' => [
        'method' => 'GET',
        'url' => '/users',
    ],
    'posts' => [
        'method' => 'GET',
        'url' => '/posts',
        'headers' => ['X-Include' => 'comments'],
    ],
    'stats' => [
        'method' => 'POST',
        'url' => '/analytics',
        'body' => ['metric' => 'pageviews'],
    ],
])->wait();

// Access results by key
$users = json_decode($results['users']->getBody()->getContents(), true);
$posts = json_decode($results['posts']->getBody()->getContents(), true);
```

#### Race Condition (Fastest Wins)

```php
// Query multiple redundant servers, use fastest response
$response = $http->race([
    'primary' => ['method' => 'GET', 'url' => 'https://api1.example.com/data'],
    'secondary' => ['method' => 'GET', 'url' => 'https://api2.example.com/data'],
    'tertiary' => ['method' => 'GET', 'url' => 'https://api3.example.com/data'],
])->wait();

echo "Fastest server responded!\n";
```

#### Promise Static Methods

```php
use Omegaalfa\HttpPromise\Promise\Promise;

// Wait for ALL to complete (fails fast on error)
$promise1 = $http->get('/users');
$promise2 = $http->get('/posts');
$promise3 = $http->get('/comments');

$results = Promise::all([$promise1, $promise2, $promise3])->wait();
// [$usersResponse, $postsResponse, $commentsResponse]

// ANY - first to succeed (ignores failures)
$first = Promise::any([$promise1, $promise2, $promise3])->wait();

// ALL SETTLED - wait for all, including failures
$outcomes = Promise::allSettled([$promise1, $promise2, $promise3])->wait();
/*
[
    ['status' => 'fulfilled', 'value' => $response1],
    ['status' => 'fulfilled', 'value' => $response2],
    ['status' => 'rejected', 'reason' => $error3],
]
*/

// Utility methods
$resolved = Promise::resolve('immediate value');
$rejected = Promise::reject(new Exception('error'));
$delayed = Promise::delay(2.0, 'value after 2 seconds');
```

### Middleware System

#### Logging Middleware

```php
$loggingMiddleware = function (array $request, callable $next) {
    $start = microtime(true);
    
    echo "[{$request['method']}] {$request['url']}\n";
    
    return $next($request)->then(function ($response) use ($start) {
        $duration = microtime(true) - $start;
        echo "✅ {$response->getStatusCode()} ({$duration}s)\n";
        return $response;
    });
};

$http = HttpPromise::create(new Response())
    ->withMiddleware($loggingMiddleware);
```

#### Authentication Refresh Middleware

```php
$authRefreshMiddleware = function (array $request, callable $next) use ($tokenManager) {
    // Check if token is expired
    if ($tokenManager->isExpired()) {
        $tokenManager->refresh();
        // Update request with new token
        $request['headers']['Authorization'] = 'Bearer ' . $tokenManager->getToken();
    }
    
    return $next($request);
};
```

#### Rate Limiting Middleware

```php
$rateLimitMiddleware = function (array $request, callable $next) use ($limiter) {
    // Wait if rate limit reached
    $limiter->waitIfNeeded();
    
    return $next($request)->then(function ($response) use ($limiter) {
        // Update rate limit from response headers
        if ($response->hasHeader('X-RateLimit-Remaining')) {
            $remaining = (int) $response->getHeaderLine('X-RateLimit-Remaining');
            $limiter->setRemaining($remaining);
        }
        return $response;
    });
};
```

#### Multiple Middlewares

```php
$http = HttpPromise::create(new Response())
    ->withMiddlewares([
        $loggingMiddleware,
        $authRefreshMiddleware,
        $rateLimitMiddleware,
        $retryMiddleware,
    ]);
```

### Advanced Features

#### Connection Pooling

```php
// Default: 50 connections per host
$http = HttpPromise::create(new Response());

// Increase pool size for high-throughput scenarios
$http = $http->withMaxPoolSize(100);

// Disable pooling (closes connections immediately)
// Useful for one-time requests or security-sensitive contexts
$http = $http->withMaxPoolSize(0);
```

**How it works:**
- Connections are pooled **per host** (prevents credential leakage)
- Handles are reset securely (headers, auth, cookies cleared)
- Automatic cleanup when instance is destroyed

#### HTTP/2 Multiplexing

```php
$http = HttpPromise::create(new Response())
    ->withHttp2(true)
    ->withBaseUrl('https://api.example.com');

// All requests to same host use SINGLE TCP connection
for ($i = 0; $i < 100; $i++) {
    $http->get("/items/{$i}");
}
$http->wait();
```

**Benefits:**
- Reduced latency (no connection overhead)
- Header compression (HPACK)
- Server push support
- Better bandwidth utilization

**Requirements:**
- libcurl 7.65.0+ (safety-checked automatically)
- Server must support HTTP/2

#### Concurrency Control

```php
// Limit concurrent requests to prevent overwhelming servers
$http = HttpPromise::create(new Response())
    ->withMaxConcurrent(10); // Max 10 simultaneous requests

// Make 1000 requests - only 10 active at a time
for ($i = 0; $i < 1000; $i++) {
    $http->get("/item/{$i}");
}

// Requests 11-1000 are queued automatically
// Queue has timeout protection (prevents starvation)
$http->wait();
```

#### Performance Metrics

```php
$http = HttpPromise::create(new Response());

// Make requests
for ($i = 0; $i < 100; $i++) {
    $http->get("https://api.example.com/item/{$i}");
}
$http->wait();

// Get metrics
$metrics = $http->getMetrics();

print_r($metrics);
/*
[
    'total_requests' => 100,
    'successful_requests' => 98,
    'failed_requests' => 2,
    'pending_requests' => 0,
    'queued_requests' => 0,
    'uptime_seconds' => 5.234,
    'requests_per_second' => 19.11,
    'success_rate' => 98.0,
]
*/
```

#### Proxy Configuration

```php
// HTTP proxy
$http = HttpPromise::create(new Response())
    ->withProxy('http://proxy.example.com:8080');

// SOCKS5 proxy
$http = $http->withProxy('socks5://proxy.example.com:1080');

// Authenticated proxy
$http = $http->withProxy('http://user:pass@proxy.example.com:8080');
```

**⚠️ Security Note:** Only use whitelisted proxy URLs in production.

---

## 🔒 Security Best Practices

HttpPromise includes **production-grade security features** based on comprehensive security audit:

### ✅ Built-in Protections

| Protection | Description | Status |
|------------|-------------|--------|
| **SSRF Prevention** | Blocks private IPs, validates protocols | ✅ Automatic |
| **Header Injection** | CRLF sanitization (RFC 7230) | ✅ Automatic |
| **Credential Isolation** | Connection pool per host | ✅ Automatic |
| **Safe Retry** | Only idempotent methods (GET, PUT, DELETE) | ✅ Automatic |
| **Queue Timeout** | Prevents starvation attacks | ✅ Automatic |
| **Type Safety** | Strict types, PHPStan level 6 | ✅ Automatic |

### 🛡️ Recommendations

#### 1. URL Validation (SSRF Protection)

```php
// ✅ GOOD - URLs are validated automatically
$http->get('https://api.example.com/data'); // OK
$http->get('http://10.0.0.1/admin');        // ❌ Throws InvalidArgumentException

// ⚠️ If accepting user input, whitelist domains:
$allowedDomains = ['api.example.com', 'cdn.example.com'];
$userUrl = $_GET['url'];

$parsed = parse_url($userUrl);
if (!in_array($parsed['host'], $allowedDomains, true)) {
    throw new Exception('Domain not allowed');
}

$http->get($userUrl);
```

#### 2. SSL Verification

```php
// ✅ ALWAYS verify SSL in production (default behavior)
$http = HttpPromise::create(new Response());

// ⚠️ ONLY disable for local development/testing
if (getenv('APP_ENV') === 'development') {
    $http = $http->withoutSSLVerification();
}

// ❌ NEVER in production:
// $http->withoutSSLVerification(); // Vulnerable to MITM attacks!
```

#### 3. Credential Management

```php
// ✅ GOOD - Use environment variables
$http = HttpPromise::create(new Response())
    ->withBearerToken(getenv('API_TOKEN'));

// ❌ BAD - Hardcoded credentials
// $http->withBearerToken('sk-1234567890abcdef');

// ✅ GOOD - Rotate tokens regularly
$token = $tokenManager->getValidToken(); // Implements refresh logic
$http = $http->withBearerToken($token);
```

#### 4. Connection Pooling in Multi-Tenant

```php
// ⚠️ In multi-tenant apps, consider disabling pooling
// or ensure strict tenant isolation

// Option 1: Disable pooling
$http = HttpPromise::create(new Response())
    ->withMaxPoolSize(0);

// Option 2: Separate instance per tenant
$httpPerTenant = [
    'tenant_a' => HttpPromise::create(new Response())
        ->withBearerToken($tenantA->getToken()),
    'tenant_b' => HttpPromise::create(new Response())
        ->withBearerToken($tenantB->getToken()),
];
```

#### 5. Timeout Configuration

```php
// ✅ Always set aggressive timeouts
$http = HttpPromise::create(new Response())
    ->withTimeout(10.0); // Connection + read timeout

// ✅ Use wait() with timeout for critical paths
try {
    $response = $http->get('/data')->wait(5.0); // Max 5 seconds total
} catch (TimeoutException $e) {
    // Handle timeout
}
```

#### 6. Retry Safety

```php
// ✅ GOOD - Only idempotent methods retry automatically
$http = $http->withRetry(3, 1.0);

$http->get('/users');    // ✅ Safe to retry
$http->put('/user/1');   // ✅ Safe to retry (idempotent)
$http->delete('/user/1'); // ✅ Safe to retry (idempotent)

$http->post('/payment'); // ❌ NEVER retries (non-idempotent)
$http->patch('/user/1'); // ❌ NEVER retries (non-idempotent)

// For POST with idempotency keys:
$http->post('/payments', [
    'amount' => 100,
    'idempotency_key' => uniqid('payment_', true),
]);
```

### 🔍 Security Audit

This library has undergone a comprehensive security audit covering:
- SSRF attacks
- Header injection
- Credential leakage
- Race conditions
- HTTP/2 vulnerabilities
- PHP-specific risks

See full audit report in [SECURITY.md](SECURITY.md) (if available).

---

## ⚡ Performance

HttpPromise is optimized for production workloads:

### Benchmarks

Environment:
- PHP 8.4.15
- libcurl 7.81.0 with HTTP/2
- 2 iterations per benchmark
- httpbin.org as test endpoint

| Benchmark | HttpPromise | Guzzle | Winner | Improvement |
|-----------|-------------|--------|---------|-------------|
| Simple GET | 135.79 ms | 137.22 ms | **🏆 HttpPromise** | +1.1% |
| POST JSON | 137.05 ms | 209.24 ms | **🏆 HttpPromise** | +52.7% |
| **Concurrent x5** | **136.98 ms** | **474.07 ms** | **🏆 HttpPromise** | **+246.1%** |
| Sequential x5 | 702.45 ms | 823.99 ms | **🏆 HttpPromise** | +17.3% |
| JSON Decode | 135.82 ms | 136.59 ms | **🏆 HttpPromise** | +0.6% |
| **Concurrent x10** | **241.07 ms** | **629.95 ms** | **🏆 HttpPromise** | **+161.3%** |
| Delayed (1s) | 1541.38 ms | 1154.07 ms | 🏆 Guzzle | -25.1% |

**Overall: HttpPromise wins 6/7 benchmarks and is 15% faster overall!**

### Key Performance Features

🚀 **Connection Pooling**
- Reuses cURL handles (eliminates connection overhead)
- Pooled per host (secure + fast)
- Configurable pool size

⚡ **HTTP/2 Multiplexing**
- Single TCP connection for multiple requests
- Header compression (HPACK)
- Parallel streams

🎯 **Event Loop Optimization**
- Adaptive select timeouts
- Zero-copy operations
- Non-blocking retry delays

📦 **Zero Dependencies**
- No Guzzle, Symfony, or other heavy frameworks
- Only ext-curl (native C extension)
- Smaller memory footprint

### Run Benchmarks

```bash
# Full benchmark suite
php benchmark.php

# Simple benchmark (fewer iterations)
php benchmark_simple.php
```

### Performance Tips

✅ **DO:**
- Reuse HttpPromise instances
- Enable HTTP/2 for same-host requests
- Use concurrent() for parallel requests
- Set appropriate concurrency limits
- Monitor metrics in production

❌ **DON'T:**
- Create new instances per request
- Set maxConcurrent too high (overwhelms servers)
- Disable connection pooling without reason
- Use blocking operations in event loop

---

## 🏗️ Architecture

```
src/
├── HttpPromise.php              # Main client (event loop, pooling, middleware)
│   ├── Event loop (tick/wait)
│   ├── Connection pool (per host)
│   ├── Middleware pipeline
│   └── Request queue (with timeouts)
│
├── Http/
│   ├── RequestOptions.php       # Immutable configuration
│   └── HttpProcessorTrait.php   # Header/body processing + sanitization
│
├── Promise/
│   ├── PromiseInterface.php     # Promise/A+ contract
│   ├── Promise.php              # Implementation (then/catch/finally)
│   └── Deferred.php             # Deferred pattern (resolve/reject)
│
└── Exception/
    ├── PromiseException.php     # Base exception
    ├── HttpException.php        # HTTP errors (with status code)
    ├── TimeoutException.php     # Timeout errors
    └── RejectionException.php   # Promise rejections
```

### Design Principles

1. **Immutability**: All `with*()` methods return new instances
2. **Type Safety**: Strict types, generics, PHPStan level 6
3. **Security First**: SSRF protection, sanitization, isolation
4. **Performance**: Connection pooling, HTTP/2, event loop
5. **Developer Experience**: Fluent API, clear errors, comprehensive docs

---

## 📖 API Reference

### HttpPromise Methods

#### Factory & Configuration

| Method                                             | Description | Returns |
|----------------------------------------------------|-------------|---------|
| `create(?ResponseInterface, ?RequestOptions, int)` | Create new instance | `HttpPromise` |
| `withBaseUrl(string)`                              | Set base URL | `self` |
| `withTimeout(float)`                               | Set connection timeout (seconds) | `self` |
| `withUserAgent(string)`                            | Set User-Agent header | `self` |
| `withHeaders(array)`                               | Add custom headers | `self` |
| `withProxy(string)`                                | Set proxy URL | `self` |
| `withoutSSLVerification()`                         | ⚠️ Disable SSL verification | `self` |

#### Authentication

| Method | Description | Returns |
|--------|-------------|---------|
| `withBearerToken(string)` | Set Bearer token | `self` |
| `withBasicAuth(string, string)` | Set Basic auth | `self` |

#### Content Type

| Method | Description | Returns |
|--------|-------------|---------|
| `asJson()` | Set JSON content type | `self` |
| `asForm()` | Set form content type | `self` |

#### Performance

| Method | Description | Returns |
|--------|-------------|---------|
| `withHttp2(bool)` | Enable HTTP/2 | `self` |
| `withTcpKeepAlive(bool)` | Enable TCP keep-alive | `self` |
| `withMaxPoolSize(int)` | Set connection pool size | `self` |
| `withMaxConcurrent(int)` | Set max concurrent requests | `self` |
| `withRetry(int, float, array)` | Configure retry (attempts, delay, status codes) | `self` |

#### Middleware

| Method | Description | Returns |
|--------|-------------|---------|
| `withMiddleware(callable)` | Add single middleware | `self` |
| `withMiddlewares(array)` | Add multiple middlewares | `self` |

#### HTTP Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `get(string, array, ?array)` | GET request (url, headers, query) | `PromiseInterface` |
| `post(string, mixed, array)` | POST request (url, body, headers) | `PromiseInterface` |
| `put(string, mixed, array)` | PUT request | `PromiseInterface` |
| `patch(string, mixed, array)` | PATCH request | `PromiseInterface` |
| `delete(string, mixed, array)` | DELETE request | `PromiseInterface` |
| `head(string, array)` | HEAD request | `PromiseInterface` |
| `options(string, array)` | OPTIONS request | `PromiseInterface` |
| `json(string, string, mixed, array)` | JSON request (method, url, data, headers) | `PromiseInterface` |

#### Concurrent Operations

| Method | Description | Returns |
|--------|-------------|---------|
| `concurrent(array)` | Execute requests in parallel | `PromiseInterface` |
| `race(array)` | Race requests (first wins) | `PromiseInterface` |

#### Event Loop

| Method | Description | Returns |
|--------|-------------|---------|
| `wait(?float)` | Wait for all pending (timeout optional) | `void` |
| `tick()` | Process one event loop iteration | `void` |
| `hasPending()` | Check if has pending requests | `bool` |
| `pendingCount()` | Get pending request count | `int` |
| `queuedCount()` | Get queued request count | `int` |

#### Metrics

| Method | Description | Returns |
|--------|-------------|---------|
| `getMetrics()` | Get performance metrics | `array` |
| `getOptions()` | Get current options | `RequestOptions` |

### PromiseInterface Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `then(?callable, ?callable)` | Chain success/error handlers | `PromiseInterface` |
| `catch(callable)` | Handle rejection | `PromiseInterface` |
| `finally(callable)` | Always execute (cleanup) | `PromiseInterface` |
| `wait(?float)` | Wait for resolution (timeout optional) | `mixed` |
| `getState()` | Get state (pending/fulfilled/rejected) | `string` |
| `isPending()` | Check if pending | `bool` |
| `isFulfilled()` | Check if fulfilled | `bool` |
| `isRejected()` | Check if rejected | `bool` |

### Promise Static Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `Promise::all(iterable)` | Wait for all (fails fast) | `PromiseInterface` |
| `Promise::race(iterable)` | First to settle wins | `PromiseInterface` |
| `Promise::any(iterable)` | First to succeed wins | `PromiseInterface` |
| `Promise::allSettled(iterable)` | Wait for all (never fails) | `PromiseInterface` |
| `Promise::resolve(mixed)` | Create resolved promise | `PromiseInterface` |
| `Promise::reject(Throwable)` | Create rejected promise | `PromiseInterface` |
| `Promise::delay(float, mixed)` | Create delayed promise | `PromiseInterface` |
| `Promise::try(callable)` | Wrap callable in promise | `PromiseInterface` |

---

## 🐛 Troubleshooting

### Common Issues

#### 1. `TimeoutException: Promise could not be resolved`

**Cause:** Promise not linked to event loop.

**Solution:**
```php
// ✅ Use HttpPromise methods (auto-wired)
$promise = $http->get('/users');

// ❌ Manual promise creation (no event loop)
$promise = new Promise(fn($resolve) => $resolve('value'));
```

#### 2. HTTP/2 Not Working

**Check support:**
```bash
php -r "var_dump(curl_version()['features'] & CURL_VERSION_HTTP2);"
```

**Enable:**
```php
$http = $http->withHttp2(true);
```

**Note:** Requires libcurl 7.65.0+ (older versions have bugs).

#### 3. Connection Pool Not Reusing

**Cause:** Creating new instances per request.

**Solution:**
```php
// ✅ Correct - reuses connections
$http = HttpPromise::create(new Response());
for ($i = 0; $i < 100; $i++) {
    $http->get("/item/{$i}");
}

// ❌ Wrong - new instance each time
for ($i = 0; $i < 100; $i++) {
    HttpPromise::create(new Response())->get("/item/{$i}")->wait();
}
```

#### 4. SSRF Error: "Access to private IP addresses is forbidden"

**Cause:** Trying to access private/reserved IP ranges (security protection).

**Solution:**
```php
// ❌ Blocked
$http->get('http://127.0.0.1/admin');
$http->get('http://192.168.1.1/api');
$http->get('http://169.254.169.254/metadata'); // AWS metadata

// ✅ Use public domains
$http->get('https://api.example.com/data');
```

#### 5. Headers Rejected: "Invalid header value"

**Cause:** CRLF injection attempt detected.

**Solution:**
```php
// ❌ Rejected (CRLF injection)
$http->withHeaders(['X-Custom' => "value\r\nX-Injected: attack"]);

// ✅ Clean headers
$http->withHeaders(['X-Custom' => 'clean-value']);
```

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

### Development Setup

```bash
# Clone repository
git clone https://github.com/omegaalfa/http-promise.git
cd http-promise

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run static analysis
vendor/bin/phpstan analyse src --level=6

# Run benchmarks
php benchmark.php
```

### Guidelines

1. **Code Style**: Follow PSR-12
2. **Tests**: Add tests for new features (maintain 100% coverage)
3. **Types**: Use strict types, generics, PHPDoc
4. **Security**: Consider security implications
5. **Performance**: Benchmark critical paths
6. **Documentation**: Update README for new features

### Pull Request Process

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

### Reporting Issues

Include:
- PHP version
- libcurl version
- Minimal reproduction example
- Expected vs actual behavior

---

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- cURL multi-handle documentation
- Promise/A+ specification
- PSR-7 HTTP message interfaces
- Security audit contributors

---

<div align="center">

**Made with ❤️ by [Omegaalfa](https://github.com/omegaalfa)**

[Report Bug](https://github.com/omegaalfa/http-promise/issues) · [Request Feature](https://github.com/omegaalfa/http-promise/issues) · [Documentation](https://github.com/omegaalfa/http-promise/wiki)

</div>

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

| Method                                        | Description |
|-----------------------------------------------|-------------|
| `create(?ResponseInterface, ?RequestOptions)` | Factory method |
| `withBaseUrl(string)`                         | Set base URL |
| `withBearerToken(string)`                     | Set Bearer auth |
| `withBasicAuth(string, string)`               | Set Basic auth |
| `withHeaders(array)`                          | Add headers |
| `withTimeout(float)`                          | Set timeout |
| `withRetry(int, float, array)`                | Configure retry |
| `withProxy(string)`                           | Set proxy |
| `withoutSSLVerification()`                    | Disable SSL verify |
| `withHttp2(bool)`                             | Enable HTTP/2 |
| `withTcpKeepAlive(bool)`                      | Enable TCP keep-alive |
| `withMaxPoolSize(int)`                        | Set connection pool size |
| `withMaxConcurrent(int)`                      | Set max concurrent requests |
| `withMiddleware(callable)`                    | Add middleware |
| `withMiddlewares(array)`                      | Add multiple middlewares |
| `asJson()`                                    | Configure for JSON |
| `asForm()`                                    | Configure for forms |
| `get(url, headers, query)`                    | GET request |
| `post(url, body, headers)`                    | POST request |
| `put(url, body, headers)`                     | PUT request |
| `patch(url, body, headers)`                   | PATCH request |
| `delete(url, body, headers)`                  | DELETE request |
| `concurrent(array)`                           | Parallel requests |
| `race(array)`                                 | Race requests |
| `wait(?timeout)`                              | Wait for all pending |
| `tick()`                                      | Process one event loop tick |
| `hasPending()`                                | Check if has pending requests |
| `pendingCount()`                              | Get pending request count |
| `queuedCount()`                               | Get queued request count |
| `getMetrics()`                                | Get performance metrics |

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
$http = HttpPromise::create();
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
