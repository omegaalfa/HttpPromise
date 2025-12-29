# HttpPromise

<div align="center">

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%206-brightgreen?style=flat-square)](https://phpstan.org)

**A modern, production-ready async HTTP client for PHP with Promise/A+ support, connection pooling, and HTTP/2 multiplexing**

[Features](#-features) • [Installation](#-installation) • [Quick Start](#-quick-start) • [API Reference](#-complete-api-reference) • [Security](#-security) • [Performance](#-performance)

</div>

---

## 🎯 Why HttpPromise?

- ⚡ **246% faster** than Guzzle on concurrent requests
- 🔒 **Security-first**: SSRF protection, header sanitization, credential isolation
- 🚀 **Zero dependencies** (only ext-curl + PSR-7)
- 🎭 **Production-tested** with comprehensive refactored architecture
- 📦 **Modern PHP 8.4+** with strict types and generics
- 🔄 **True async** with Promise/A+ implementation

## ✨ Features

### Core Capabilities
- 🚀 **Async HTTP requests** with cURL multi-handle event loop
- ⚡ **Promise/A+** implementation (`then`, `catch`, `finally`)
- 🔗 **Fluent API** for elegant configuration chaining
- 🔄 **Concurrent requests** with `concurrent()` and `race()`
- 🔁 **Smart retry** - idempotent methods only, exponential backoff
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
- 🚫 **Safe retry** - only idempotent methods (GET, PUT, DELETE, HEAD, OPTIONS)
- ✅ **Type safety** - strict types, PHPStan level 6

### New Architecture (Post-Refactoring)
- 🏗️ **Separated concerns** - 6 focused classes instead of monolithic structure
- 🔧 **ConnectionPool** - Dedicated connection management
- 🔄 **EventLoop** - Isolated event processing
- 🔁 **RetryHandler** - Configurable retry logic with exponential backoff
- 📊 **Metrics** - Dedicated performance tracking
- 🛡️ **UrlValidator** - Standalone SSRF protection

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
use Laminas\Diactoros\Response;

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

## 📖 Complete API Reference

All 38 public methods documented with explanations and examples.

---

### 🏭 Factory & Initialization

#### `create(?ResponseInterface $response = null, ?RequestOptions $options = null, int $maxRetryAttempts = 3): static`

Creates a new HttpPromise instance with optional pre-configuration.

**Parameters:**
- `$response` (optional): PSR-7 response prototype (e.g., `new Response()`)
- `$options` (optional): Pre-configured RequestOptions object
- `$maxRetryAttempts` (default: 3): Maximum retry attempts for failed requests

**Returns:** New `HttpPromise` instance

**Example:**
```php
use Omegaalfa\HttpPromise\HttpPromise;
use Laminas\Diactoros\Response;

// Basic creation
$http = HttpPromise::create();

// With response prototype
$http = HttpPromise::create(new Response());

// With pre-configured options
$options = RequestOptions::create()
    ->withBaseUrl('https://api.example.com')
    ->withTimeout(30.0);
$http = HttpPromise::create(new Response(), $options);

// With custom retry limit
$http = HttpPromise::create(null, null, 5); // Max 5 retries
```

---

#### `__construct(?ResponseInterface $response = null, ?RequestOptions $options = null, int $maxRetryAttempts = 3)`

Constructor (use `create()` factory method instead for cleaner syntax).

**Example:**
```php
$http = new HttpPromise(new Response(), RequestOptions::create(), 3);
```

---

### ⚙️ Configuration Methods

All configuration methods return a **new immutable instance**.

---

#### `withBaseUrl(string $baseUrl): self`

Sets the base URL prepended to all relative URLs.

**Parameters:**
- `$baseUrl`: Base URL (e.g., `https://api.example.com`)

**Returns:** New instance with updated base URL

**Example:**
```php
$http = HttpPromise::create()
    ->withBaseUrl('https://api.github.com');

// Now all requests use the base URL
$http->get('/users/octocat')->wait();      // https://api.github.com/users/octocat
$http->get('/repos/php/php-src')->wait();  // https://api.github.com/repos/php/php-src
```

---

#### `withOptions(RequestOptions $options): self`

Replaces all options with a pre-configured RequestOptions object.

**Parameters:**
- `$options`: Complete RequestOptions configuration

**Returns:** New instance with replaced options

**Example:**
```php
$options = RequestOptions::create()
    ->withBaseUrl('https://api.example.com')
    ->withTimeout(60.0)
    ->withRetry(5, 2.0)
    ->asJson();

$http = HttpPromise::create()->withOptions($options);

// All options are now active
$http->get('/data')->wait();
```

---

#### `withTimeout(float $timeout): self`

Sets connection and read timeout in seconds.

**Parameters:**
- `$timeout`: Timeout in seconds (e.g., `30.0`)

**Returns:** New instance with updated timeout

**Example:**
```php
// Aggressive timeout for fast APIs
$http = HttpPromise::create()
    ->withTimeout(5.0);

// Longer timeout for slow operations
$http = $http->withTimeout(120.0);

// Catch timeouts
$http->get('/slow-endpoint')
    ->catch(fn(TimeoutException $e) => ['error' => 'Timeout!'])
    ->wait();
```

---

#### `withRetry(int $attempts, float $delay, array $statusCodes = [429, 502, 503, 504]): self`

Configures automatic retry with exponential backoff for idempotent methods only (GET, PUT, DELETE, HEAD, OPTIONS).

**Parameters:**
- `$attempts`: Maximum retry attempts
- `$delay`: Initial delay in seconds (doubles each retry)
- `$statusCodes`: HTTP status codes that trigger retries

**Returns:** New instance with retry configuration

**Example:**
```php
// Retry up to 3 times with 1s initial delay
$http = HttpPromise::create()
    ->withRetry(3, 1.0);

// Custom status codes
$http = $http->withRetry(5, 2.0, [408, 429, 500, 502, 503, 504]);

// GET request retries on failure
$http->get('/unreliable-api')->wait();  // Retries automatically

// POST never retries (non-idempotent)
$http->post('/payment', ['amount' => 100])->wait();  // No retry
```

**Retry Schedule:**
- Attempt 1: Immediate
- Attempt 2: After 1s delay
- Attempt 3: After 2s delay (exponential backoff)
- Attempt 4: After 4s delay

---

#### `withHeaders(array $headers): self`

Adds custom headers to all requests. Merges with existing headers.

**Parameters:**
- `$headers`: Associative array of headers

**Returns:** New instance with merged headers

**Example:**
```php
$http = HttpPromise::create()
    ->withHeaders([
        'Accept' => 'application/json',
        'X-API-Version' => '2.0',
        'X-Request-ID' => uniqid(),
    ]);

// Add more headers later
$http = $http->withHeaders([
    'X-Tenant-ID' => 'tenant-123',
]);

// All headers are sent with every request
$http->get('/data')->wait();
```

---

#### `withProxy(string $proxy): self`

Configures HTTP/SOCKS proxy for all requests.

**Parameters:**
- `$proxy`: Proxy URL (e.g., `http://proxy.example.com:8080`)

**Returns:** New instance with proxy configuration

**Example:**
```php
// HTTP proxy
$http = HttpPromise::create()
    ->withProxy('http://proxy.company.com:8080');

// SOCKS5 proxy
$http = $http->withProxy('socks5://127.0.0.1:1080');

// Authenticated proxy
$http = $http->withProxy('http://user:pass@proxy.company.com:3128');
```

**⚠️ Security:** Only use whitelisted proxy URLs in production.

---

#### `withoutSSLVerification(): self`

**⚠️ WARNING:** Disables SSL certificate verification (vulnerable to MITM attacks).

**Returns:** New instance with SSL verification disabled

**Example:**
```php
// ❌ NEVER in production
// ✅ ONLY for local development/testing
if (getenv('APP_ENV') === 'development') {
    $http = HttpPromise::create()->withoutSSLVerification();
}

// Use for self-signed certificates in staging
$http->get('https://staging.local')->wait();
```

---

#### `withUserAgent(string $userAgent): self`

Sets custom User-Agent header.

**Parameters:**
- `$userAgent`: User-Agent string

**Returns:** New instance with custom User-Agent

**Example:**
```php
$http = HttpPromise::create()
    ->withUserAgent('MyApp/1.0 (https://example.com)');

// Different user agents for different contexts
$mobileHttp = $http->withUserAgent('MyApp-Mobile/2.0');
$botHttp = $http->withUserAgent('MyBot/1.0 (+https://example.com/bot)');
```

---

### 🔐 Authentication Methods

---

#### `withBearerToken(string $token): self`

Adds Bearer token authentication (OAuth 2.0, JWT).

**Parameters:**
- `$token`: Bearer token string

**Returns:** New instance with Authorization header

**Example:**
```php
// OAuth 2.0 / JWT
$http = HttpPromise::create()
    ->withBearerToken('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...');

$http->get('/protected-resource')->wait();

// Token rotation
$newToken = $authService->refreshToken();
$http = $http->withBearerToken($newToken);
```

---

#### `withBasicAuth(string $username, string $password): self`

Adds HTTP Basic Authentication.

**Parameters:**
- `$username`: Username
- `$password`: Password

**Returns:** New instance with Authorization header

**Example:**
```php
$http = HttpPromise::create()
    ->withBasicAuth('admin', 'secret123');

$http->get('/admin/dashboard')->wait();

// For APIs using Basic Auth
$http = HttpPromise::create()
    ->withBasicAuth('api-key-id', 'api-secret-key');
```

---

### 🎨 Content Type Methods

---

#### `asJson(): self`

Sets content type to `application/json` and auto-encodes request bodies.

**Returns:** New instance with JSON content type

**Example:**
```php
$http = HttpPromise::create()->asJson();

// Body is automatically JSON-encoded
$http->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'roles' => ['admin', 'editor'],
])->wait();

// Equivalent to:
// Content-Type: application/json
// Body: {"name":"John Doe","email":"john@example.com","roles":["admin","editor"]}
```

---

#### `asForm(): self`

Sets content type to `application/x-www-form-urlencoded`.

**Returns:** New instance with form content type

**Example:**
```php
$http = HttpPromise::create()->asForm();

// Body is URL-encoded
$http->post('/login', [
    'username' => 'john@example.com',
    'password' => 'secret123',
])->wait();

// Equivalent to:
// Content-Type: application/x-www-form-urlencoded
// Body: username=john%40example.com&password=secret123
```

---

### ⚡ Performance & Concurrency

---

#### `withHttp2(bool $enable = true): self`

Enables HTTP/2 multiplexing (requires libcurl 7.65.0+).

**Parameters:**
- `$enable` (default: true): Enable or disable HTTP/2

**Returns:** New instance with HTTP/2 configuration

**Example:**
```php
$http = HttpPromise::create()
    ->withHttp2(true)
    ->withBaseUrl('https://api.example.com');

// All requests to same host use SINGLE TCP connection
for ($i = 0; $i < 100; $i++) {
    $http->get("/items/$i");
}
$http->wait();  // 100 requests over 1 connection!

// Benefits:
// - Lower latency (no connection overhead)
// - Header compression (HPACK)
// - Server push support
```

**⚠️ Requirements:**
- libcurl 7.65.0+ (older versions have multiplexing bugs)
- Server must support HTTP/2

---

#### `withTcpKeepAlive(bool $enable = true): self`

Enables TCP keep-alive to detect dead connections.

**Parameters:**
- `$enable` (default: true): Enable or disable TCP keep-alive

**Returns:** New instance with TCP keep-alive configuration

**Example:**
```php
$http = HttpPromise::create()
    ->withTcpKeepAlive(true);

// Prevents idle connections from being killed by firewalls
$http->get('/long-polling')->wait();
```

---

#### `withMaxPoolSize(int $size): self`

Sets maximum connection pool size per host.

**Parameters:**
- `$size`: Max connections per host (0 = disable pooling)

**Returns:** New instance with updated pool size

**Example:**
```php
// Optimize for high throughput
$http = HttpPromise::create()
    ->withMaxPoolSize(100);

// Disable pooling (multi-tenant isolation)
$http = $http->withMaxPoolSize(0);

// Conservative pooling
$http = $http->withMaxPoolSize(10);
```

---

#### `withMaxConcurrent(int $limit): self`

Limits maximum concurrent requests (excess requests are queued).

**Parameters:**
- `$limit`: Max simultaneous requests

**Returns:** New instance with concurrency limit

**Example:**
```php
$http = HttpPromise::create()
    ->withMaxConcurrent(10);  // Max 10 at once

// Make 1000 requests
for ($i = 0; $i < 1000; $i++) {
    $http->get("https://api.example.com/item/$i");
}

// Only 10 execute simultaneously, rest are queued
$http->wait();
```

---

#### `withMiddleware(callable $middleware): self`

Adds middleware to request/response pipeline.

**Parameters:**
- `$middleware`: Callable `function(array $request, callable $next): PromiseInterface`

**Returns:** New instance with added middleware

**Example:**
```php
// Logging middleware
$loggingMiddleware = function (array $request, callable $next) {
    echo "[{$request['method']}] {$request['url']}\n";
    
    return $next($request)->then(function ($response) {
        echo "✅ {$response->getStatusCode()}\n";
        return $response;
    });
};

$http = HttpPromise::create()
    ->withMiddleware($loggingMiddleware);

// Retry middleware
$retryMiddleware = function (array $request, callable $next) {
    return $next($request)->catch(function ($e) use ($request, $next) {
        if ($e instanceof TimeoutException) {
            echo "Retrying after timeout...\n";
            return $next($request);  // Retry once
        }
        throw $e;
    });
};

$http = $http->withMiddleware($retryMiddleware);
```

---

#### `withMiddlewares(array $middlewares): self`

Adds multiple middlewares at once.

**Parameters:**
- `$middlewares`: Array of middleware callables

**Returns:** New instance with all middlewares added

**Example:**
```php
$http = HttpPromise::create()->withMiddlewares([
    $authMiddleware,
    $loggingMiddleware,
    $rateLimitMiddleware,
    $cachingMiddleware,
]);
```

---

### 🌐 HTTP Request Methods

All request methods return a **PromiseInterface** that must be resolved with `wait()` or chained with `then()`.

---

#### `get(string $url, array $headers = [], array $queryParams = []): PromiseInterface`

Sends an HTTP GET request.

**Parameters:**
- `$url`: Request URL (absolute or relative to baseUrl)
- `$headers`: Additional headers for this request
- `$queryParams`: Query string parameters

**Returns:** Promise that resolves to PSR-7 ResponseInterface

**Example:**
```php
$http = HttpPromise::create()
    ->withBaseUrl('https://api.github.com');

// Simple GET
$response = $http->get('/users/octocat')->wait();

// With query parameters
$response = $http->get('/search/users', [], [
    'q' => 'location:San Francisco',
    'per_page' => 50,
])->wait();
// URL: /search/users?q=location%3ASan+Francisco&per_page=50

// With custom headers
$response = $http->get('/repos/php/php-src', [
    'Accept' => 'application/vnd.github.v3+json',
])->wait();

// Promise chaining
$users = $http->get('/users')
    ->then(fn($response) => json_decode($response->getBody()->getContents(), true))
    ->then(fn($data) => array_column($data, 'login'))
    ->wait();
```

---

#### `post(string $url, array $body = [], array $headers = []): PromiseInterface`

Sends an HTTP POST request.

**Parameters:**
- `$url`: Request URL
- `$body`: Request body (array auto-encoded based on content type)
- `$headers`: Additional headers

**Returns:** Promise that resolves to ResponseInterface

**Example:**
```php
$http = HttpPromise::create()->asJson();

// Create resource
$response = $http->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
])->wait();

// Form submission
$http = HttpPromise::create()->asForm();
$response = $http->post('/login', [
    'username' => 'john',
    'password' => 'secret',
])->wait();

// With custom headers
$response = $http->post('/webhooks', ['event' => 'user.created'], [
    'X-Webhook-Signature' => hash_hmac('sha256', $body, $secret),
])->wait();
```

**⚠️ Note:** POST requests are **never retried** (non-idempotent).

---

#### `put(string $url, array $body = [], array $headers = []): PromiseInterface`

Sends an HTTP PUT request (idempotent update).

**Parameters:**
- `$url`: Request URL
- `$body`: Request body
- `$headers`: Additional headers

**Returns:** Promise that resolves to ResponseInterface

**Example:**
```php
$http = HttpPromise::create()->asJson();

// Full resource update (idempotent)
$response = $http->put('/users/123', [
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'status' => 'active',
])->wait();

// Safe to retry on failure
$http = $http->withRetry(3, 1.0);
$http->put('/users/123', $userData)->wait();  // Retries automatically
```

**✅ Safe to retry:** PUT is idempotent.

---

#### `patch(string $url, array $body = [], array $headers = []): PromiseInterface`

Sends an HTTP PATCH request (partial update).

**Parameters:**
- `$url`: Request URL
- `$body`: Partial update data
- `$headers`: Additional headers

**Returns:** Promise that resolves to ResponseInterface

**Example:**
```php
$http = HttpPromise::create()->asJson();

// Partial update
$response = $http->patch('/users/123', [
    'email' => 'newemail@example.com',  // Only update email
])->wait();
```

**⚠️ Note:** PATCH requests are **never retried** (non-idempotent).

---

#### `delete(string $url, array $headers = []): PromiseInterface`

Sends an HTTP DELETE request.

**Parameters:**
- `$url`: Request URL
- `$headers`: Additional headers

**Returns:** Promise that resolves to ResponseInterface

**Example:**
```php
$http = HttpPromise::create();

// Delete resource
$response = $http->delete('/users/123')->wait();

// With authorization
$response = $http->delete('/admin/users/123', [
    'X-Admin-Token' => $adminToken,
])->wait();
```

**✅ Safe to retry:** DELETE is idempotent.

---

#### `head(string $url, array $headers = []): PromiseInterface`

Sends an HTTP HEAD request (metadata only, no body).

**Parameters:**
- `$url`: Request URL
- `$headers`: Additional headers

**Returns:** Promise that resolves to ResponseInterface (empty body)

**Example:**
```php
$http = HttpPromise::create();

// Check if resource exists
$response = $http->head('/users/123')->wait();
if ($response->getStatusCode() === 200) {
    echo "User exists\n";
}

// Get file metadata
$response = $http->head('https://example.com/large-file.zip')->wait();
$fileSize = $response->getHeaderLine('Content-Length');
$lastModified = $response->getHeaderLine('Last-Modified');
```

---

#### `options(string $url, array $headers = []): PromiseInterface`

Sends an HTTP OPTIONS request (CORS preflight, capability check).

**Parameters:**
- `$url`: Request URL
- `$headers`: Additional headers

**Returns:** Promise that resolves to ResponseInterface

**Example:**
```php
$http = HttpPromise::create();

// Check CORS
$response = $http->options('/api/users')->wait();
$allowedMethods = $response->getHeaderLine('Allow');
echo "Allowed methods: $allowedMethods\n";  // GET, POST, PUT, DELETE

// CORS preflight
$response = $http->options('/api/users', [
    'Access-Control-Request-Method' => 'POST',
    'Access-Control-Request-Headers' => 'X-Custom-Header',
])->wait();
```

---

#### `request(string $method, string $url, array $options = []): PromiseInterface`

Generic HTTP request method for custom verbs or advanced options.

**Parameters:**
- `$method`: HTTP method (GET, POST, CUSTOM, etc.)
- `$url`: Request URL
- `$options`: Array with keys: `headers`, `body`, `query`

**Returns:** Promise that resolves to ResponseInterface

**Example:**
```php
$http = HttpPromise::create();

// Custom HTTP method
$response = $http->request('PROPFIND', '/webdav/files', [
    'headers' => ['Depth' => '1'],
])->wait();

// Complex request
$response = $http->request('POST', '/api/users', [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-Request-ID' => uniqid(),
    ],
    'body' => json_encode(['name' => 'John']),
    'query' => ['notify' => 'true'],
])->wait();
```

---

#### `json(string $method, string $url, array $data = [], array $headers = []): PromiseInterface`

Convenience method for JSON requests.

**Parameters:**
- `$method`: HTTP method
- `$url`: Request URL
- `$data`: Data to JSON-encode
- `$headers`: Additional headers

**Returns:** Promise that resolves to ResponseInterface

**Example:**
```php
$http = HttpPromise::create();

// Automatic JSON encoding
$response = $http->json('POST', '/api/users', [
    'name' => 'John Doe',
    'metadata' => ['role' => 'admin'],
])->wait();

// Equivalent to:
$http->asJson()->post('/api/users', ['name' => 'John Doe'])->wait();
```

---

### 🔄 Concurrency Methods

---

#### `concurrent(array $requests): PromiseInterface`

Executes multiple requests in parallel and returns results as associative array.

**Parameters:**
- `$requests`: Associative array of request configurations

**Returns:** Promise that resolves to `array<string, ResponseInterface>`

**Example:**
```php
$http = HttpPromise::create()->withMaxConcurrent(10);

$results = $http->concurrent([
    'users' => [
        'method' => 'GET',
        'url' => 'https://api.example.com/users',
    ],
    'posts' => [
        'method' => 'GET',
        'url' => 'https://api.example.com/posts',
        'headers' => ['X-Include' => 'comments'],
    ],
    'stats' => [
        'method' => 'POST',
        'url' => 'https://api.example.com/stats',
        'body' => ['metric' => 'pageviews'],
    ],
])->wait();

// Access by key
$users = json_decode($results['users']->getBody()->getContents(), true);
$posts = json_decode($results['posts']->getBody()->getContents(), true);
$stats = json_decode($results['stats']->getBody()->getContents(), true);
```

**Error Handling:**
```php
$http->concurrent($requests)
    ->catch(function ($error) {
        // If ANY request fails, promise rejects
        echo "One request failed: " . $error->getMessage();
    })
    ->wait();
```

---

#### `race(array $requests): PromiseInterface`

Executes requests in parallel, returns the **first successful response**.

**Parameters:**
- `$requests`: Associative array of request configurations

**Returns:** Promise that resolves to first successful ResponseInterface

**Example:**
```php
$http = HttpPromise::create();

// Query redundant servers, use fastest
$response = $http->race([
    'primary' => [
        'method' => 'GET',
        'url' => 'https://api1.example.com/data',
    ],
    'secondary' => [
        'method' => 'GET',
        'url' => 'https://api2.example.com/data',
    ],
    'tertiary' => [
        'method' => 'GET',
        'url' => 'https://api3.example.com/data',
    ],
])->wait();

echo "Fastest server responded!\n";

// Fallback pattern
$response = $http->race([
    'cache' => ['method' => 'GET', 'url' => 'http://cache.local/data'],
    'origin' => ['method' => 'GET', 'url' => 'https://api.example.com/data'],
])->wait();
```

---

### ⏱️ Event Loop Control

---

#### `tick(): void`

Processes pending requests in the event loop (non-blocking).

**Returns:** void

**Example:**
```php
$http = HttpPromise::create();

// Start async requests
$promise1 = $http->get('https://api1.example.com/data');
$promise2 = $http->get('https://api2.example.com/data');

// Manual event loop
while ($http->hasPending()) {
    $http->tick();  // Process events
    
    // Do other work
    echo "Working...\n";
    usleep(10000);
}

// Wait for results
$result1 = $promise1->wait();
$result2 = $promise2->wait();
```

---

#### `wait(?float $timeout = null): void`

Blocks until all pending requests complete or timeout.

**Parameters:**
- `$timeout` (optional): Maximum wait time in seconds

**Returns:** void

**Throws:** `TimeoutException` if timeout is exceeded

**Example:**
```php
$http = HttpPromise::create();

// Start requests
$http->get('https://api.example.com/users');
$http->get('https://api.example.com/posts');

// Wait for ALL to complete
$http->wait();

// With timeout
try {
    $http->wait(5.0);  // Max 5 seconds
} catch (TimeoutException $e) {
    echo "Requests took too long!\n";
}
```

---

#### `hasPending(): bool`

Checks if there are pending requests in the event loop.

**Returns:** true if requests are pending, false otherwise

**Example:**
```php
$http = HttpPromise::create();

$http->get('https://api.example.com/data');

if ($http->hasPending()) {
    echo "Waiting for requests...\n";
    $http->wait();
}
```

---

### 📊 Monitoring & Metrics

---

#### `getMetrics(): array`

Returns performance metrics for completed requests.

**Returns:** Array with metrics

**Example:**
```php
$http = HttpPromise::create();

// Make requests
for ($i = 0; $i < 100; $i++) {
    $http->get("https://api.example.com/item/$i");
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

// Monitoring
if ($metrics['success_rate'] < 95.0) {
    alert("API success rate dropped below 95%!");
}
```

---

#### `pendingCount(): int`

Returns number of currently executing requests.

**Returns:** Count of pending requests

**Example:**
```php
$http = HttpPromise::create()->withMaxConcurrent(10);

for ($i = 0; $i < 100; $i++) {
    $http->get("https://api.example.com/item/$i");
    
    echo "Pending: " . $http->pendingCount() . "\n";  // Max 10
}
```

---

#### `queuedCount(): int`

Returns number of requests waiting in queue.

**Returns:** Count of queued requests

**Example:**
```php
$http = HttpPromise::create()->withMaxConcurrent(5);

for ($i = 0; $i < 20; $i++) {
    $http->get("https://api.example.com/item/$i");
}

echo "Pending: " . $http->pendingCount() . "\n";  // 5
echo "Queued: " . $http->queuedCount() . "\n";    // 15

$http->wait();

echo "Queued: " . $http->queuedCount() . "\n";    // 0
```

---

### 🔧 Utility Methods

---

#### `getOptions(): RequestOptions`

Returns current RequestOptions configuration.

**Returns:** RequestOptions object

**Example:**
```php
$http = HttpPromise::create()
    ->withBaseUrl('https://api.example.com')
    ->withTimeout(30.0);

$options = $http->getOptions();

// Inspect configuration
$baseUrl = $options->baseUrl;
$timeout = $options->timeout;

echo "Base URL: $baseUrl\n";
echo "Timeout: $timeout seconds\n";
```

---

#### `__destruct(): void`

Destructor - automatically closes all cURL handles and cleans up resources.

**Example:**
```php
$http = HttpPromise::create();

// Make requests...
$http->get('/data')->wait();

// Automatically cleaned up when object is destroyed
unset($http);  // Explicitly destroy (optional)
```

---

## 🏗️ Promise API

The Promise class implements Promise/A+ specification with additional utilities.

### Promise Methods

#### `then(callable $onFulfilled = null, callable $onRejected = null): PromiseInterface`

Attaches fulfillment and rejection handlers.

**Example:**
```php
$promise->then(
    fn($value) => "Success: $value",
    fn($error) => "Error: {$error->getMessage()}"
);
```

---

#### `catch(callable $onRejected): PromiseInterface`

Attaches rejection handler (shortcut for `then(null, $onRejected)`).

**Example:**
```php
$promise->catch(fn($error) => handleError($error));
```

---

#### `finally(callable $onFinally): PromiseInterface`

Executes callback regardless of fulfillment or rejection.

**Example:**
```php
$promise->finally(fn() => cleanup());
```

---

#### `wait(?float $timeout = null): mixed`

Blocks until promise resolves and returns value.

**Example:**
```php
$result = $promise->wait(10.0);  // Wait max 10 seconds
```

---

### Promise Static Methods

#### `Promise::resolve(mixed $value): PromiseInterface`

Creates immediately fulfilled promise.

**Example:**
```php
$promise = Promise::resolve(42);
$value = $promise->wait();  // 42
```

---

#### `Promise::reject(Throwable $reason): PromiseInterface`

Creates immediately rejected promise.

**Example:**
```php
$promise = Promise::reject(new Exception('Error'));
$promise->catch(fn($e) => echo $e->getMessage());
```

---

#### `Promise::all(array $promises): PromiseInterface`

Waits for ALL promises to fulfill (fails fast on first rejection).

**Example:**
```php
$promises = [
    $http->get('/users'),
    $http->get('/posts'),
    $http->get('/comments'),
];

$results = Promise::all($promises)->wait();
// [$usersResponse, $postsResponse, $commentsResponse]
```

---

#### `Promise::any(array $promises): PromiseInterface`

Returns first fulfilled promise (ignores rejections until all fail).

**Example:**
```php
$first = Promise::any([
    $http->get('https://slow-api.com/data'),
    $http->get('https://fast-api.com/data'),
])->wait();
```

---

#### `Promise::race(array $promises): PromiseInterface`

Returns first settled promise (fulfilled OR rejected).

**Example:**
```php
$fastest = Promise::race([
    $http->get('https://api1.com/data'),
    $http->get('https://api2.com/data'),
])->wait();
```

---

#### `Promise::delay(float $seconds, mixed $value = null): PromiseInterface`

Creates promise that resolves after delay.

**Example:**
```php
Promise::delay(2.0, 'Ready!')
    ->then(fn($msg) => echo $msg)
    ->wait();  // Waits 2 seconds, then echoes "Ready!"
```

---

## 🔒 Security Best Practices

See [Security](#-security-best-practices) section above for comprehensive security guide.
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

After refactoring, HttpPromise now follows a clean separation of concerns:

```
src/
├── HttpPromise.php              # Main client - orchestrates components
│   ├── Request lifecycle management
│   ├── Middleware pipeline
│   ├── Public API (get, post, concurrent, etc.)
│   └── Event loop integration
│
├── Http/
│   ├── RequestOptions.php       # Immutable configuration
│   ├── HttpProcessorTrait.php   # Header/body processing + sanitization
│   ├── ConnectionPool.php       # Connection pooling per host
│   ├── EventLoop.php            # Request queue + event loop
│   ├── RetryHandler.php         # Retry logic with exponential backoff
│   ├── Metrics.php              # Performance tracking
│   └── UrlValidator.php         # SSRF protection + URL validation
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
4. **Separation of Concerns**: Each class has a single responsibility
5. **Performance**: Connection pooling, HTTP/2, event loop optimization
6. **Developer Experience**: Fluent API, clear errors, comprehensive docs

### Component Responsibilities

**HttpPromise** - Main orchestrator
- Public API surface
- Middleware pipeline execution
- Delegates to specialized components

**ConnectionPool** - Connection management
- Per-host connection pooling
- Secure handle reset between uses
- Configurable pool size

**EventLoop** - Request execution
- Manages pending/queued requests
- cURL multi-handle operations
- Concurrency limit enforcement

**RetryHandler** - Retry logic
- Idempotent method detection
- Exponential backoff
- Configurable status codes

**Metrics** - Performance tracking
- Request counters
- Success rate calculation
- Throughput metrics

**UrlValidator** - Security
- SSRF prevention
- Private IP blocking
- Protocol whitelist

---

## 🔧 Troubleshooting

### "Promise could not be resolved"

**Cause:** Promise timeout or missing event loop integration.

**Solution:**
```php
// ✅ Increase timeout
$http = HttpPromise::create()
    ->withTimeout(60.0);

// ✅ Use explicit wait with timeout
$promise->wait(120.0);

// ✅ Check if pending
if ($http->hasPending()) {
    $http->wait();
}
```

---

### HTTP/2 Not Working

**Check support:**
```bash
php -r "echo (curl_version()['features'] & CURL_VERSION_HTTP2) ? '✅ Supported' : '❌ Not available';"
php -r "echo 'libcurl: ' . curl_version()['version'];"
```

**Requirements:**
- libcurl 7.65.0+ (older versions have multiplexing bugs)
- Compiled with nghttp2 support

**Enable:**
```php
$http = HttpPromise::create()->withHttp2(true);
```

---

### Connection Pool Not Reusing

**Cause:** Creating new HttpPromise instances per request.

**Solution:**
```php
// ✅ CORRECT - reuses connections
$http = HttpPromise::create()->withBaseUrl('https://api.example.com');
for ($i = 0; $i < 100; $i++) {
    $http->get("/item/$i");
}
$http->wait();

// ❌ WRONG - no connection reuse
for ($i = 0; $i < 100; $i++) {
    HttpPromise::create()->get("https://api.example.com/item/$i")->wait();
}
```

---

### "URL must start with http:// or https://"

**Cause:** Invalid URL or SSRF protection triggered.

**Solution:**
```php
// ✅ Use full URLs
$http->get('https://api.example.com/data')->wait();

// ✅ Or set baseUrl
$http = HttpPromise::create()
    ->withBaseUrl('https://api.example.com');
$http->get('/data')->wait();  // Combines to https://api.example.com/data

// ❌ Private IPs are blocked (SSRF protection)
$http->get('http://192.168.1.1/admin')->wait();  // Throws InvalidArgumentException
```

---

### "Maximum retry attempts exceeded"

**Cause:** Server consistently returning retryable status codes.

**Solution:**
```php
// ✅ Increase retry attempts
$http = $http->withRetry(5, 2.0);

// ✅ Customize retryable status codes
$http = $http->withRetry(3, 1.0, [429, 503]);  // Only retry these

// ✅ Add circuit breaker middleware
$circuitBreaker = function ($request, $next) {
    if (tooManyFailures()) {
        throw new Exception('Circuit breaker open');
    }
    return $next($request);
};
$http = $http->withMiddleware($circuitBreaker);
```

---

### Headers Rejected: "Invalid header value"

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
2. **Tests**: Add tests for new features
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
- libcurl version (`php -r "echo curl_version()['version'];"`)
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
- PHP community for feedback and contributions

---

<div align="center">

**Made with ❤️ by [Omegaalfa](https://github.com/omegaalfa)**

[Report Bug](https://github.com/omegaalfa/http-promise/issues) · [Request Feature](https://github.com/omegaalfa/http-promise/issues)

</div>
