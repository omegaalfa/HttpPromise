<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/TestHelper.php';

use Omegaalfa\HttpPromise\HttpPromise;
use Omegaalfa\HttpPromise\Http\RequestOptions;
use Omegaalfa\HttpPromise\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for HttpPromise HTTP client.
 */
class HttpPromiseTest extends TestCase
{
    private HttpPromise $http;

    protected function setUp(): void
    {
        $this->http = HttpPromise::create(new SimpleResponse());
    }

    public function testCreateClient(): void
    {
        $this->assertInstanceOf(HttpPromise::class, $this->http);
    }

    public function testWithBaseUrl(): void
    {
        $client = $this->http->withBaseUrl('https://api.example.com');
        $this->assertNotSame($this->http, $client);
    }

    public function testWithBearerToken(): void
    {
        $client = $this->http->withBearerToken('token123');
        $this->assertNotSame($this->http, $client);
    }

    public function testAsJson(): void
    {
        $client = $this->http->asJson();
        $this->assertNotSame($this->http, $client);
    }

    public function testPendingCount(): void
    {
        $this->assertEquals(0, $this->http->pendingCount());
    }

    public function testGetRequest(): void
    {
        $promise = $this->http->get('https://httpbin.org/get');
        $this->assertNotNull($promise);
    }

    public function testPostRequest(): void
    {
        $promise = $this->http->post('https://httpbin.org/post', ['key' => 'value']);
        $this->assertNotNull($promise);
    }

    public function testConcurrentRequests(): void
    {
        $requests = [
            ['method' => 'GET', 'url' => 'https://httpbin.org/get'],
            ['method' => 'GET', 'url' => 'https://httpbin.org/uuid'],
            ['method' => 'GET', 'url' => 'https://httpbin.org/json'],
        ];

        $promise = $this->http->concurrent($requests);
        $this->assertNotNull($promise);
    }

    public function testWithMaxConcurrent(): void
    {
        $client = $this->http->withMaxConcurrent(5);
        $this->assertNotSame($this->http, $client);
    }

    public function testWithHttp2(): void
    {
        $client = $this->http->withHttp2(true);
        $this->assertNotSame($this->http, $client);
        $this->assertTrue($client->getOptions()->http2);
    }

    public function testWithTcpKeepAlive(): void
    {
        $client = $this->http->withTcpKeepAlive(false);
        $this->assertNotSame($this->http, $client);
        $this->assertFalse($client->getOptions()->tcpKeepAlive);
    }

    public function testWithMaxPoolSize(): void
    {
        $client = $this->http->withMaxPoolSize(0);
        $this->assertNotSame($this->http, $client);
        $this->assertTrue(method_exists($client, 'withMaxPoolSize'));
    }

    public function testMiddlewareIsCalled(): void
    {
        $called = false;
        $client = $this->http->withMiddleware(function (array $request, callable $next) use (&$called) {
            $called = true;
            return $next($request);
        });

        $client->get('https://httpbin.org/get');
        $this->assertTrue($called);
    }

    public function testQueuedCount(): void
    {
        $this->assertEquals(0, $this->http->queuedCount());
    }

    public function testGetMetrics(): void
    {
        $metrics = $this->http->getMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_requests', $metrics);
        $this->assertArrayHasKey('successful_requests', $metrics);
        $this->assertArrayHasKey('failed_requests', $metrics);
        $this->assertArrayHasKey('pending_requests', $metrics);
        $this->assertArrayHasKey('queued_requests', $metrics);
        $this->assertArrayHasKey('uptime_seconds', $metrics);
        $this->assertArrayHasKey('requests_per_second', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);
    }

    public function testHasPendingWithQueued(): void
    {
        $client = $this->http->withMaxConcurrent(1);

        $this->assertFalse($client->hasPending());
        $this->assertEquals(0, $client->queuedCount());

        // First request consumes the single slot
        $client->get('https://httpbin.org/delay/1');
        $this->assertTrue($client->hasPending());

        // Second request should be queued
        $client->get('https://httpbin.org/delay/1');
        $this->assertGreaterThanOrEqual(1, $client->queuedCount());
    }

    public function testWithBasicAuth(): void
    {
        $client = $this->http->withBasicAuth('user', 'pass');
        $this->assertNotSame($this->http, $client);
    }

    public function testAsForm(): void
    {
        $client = $this->http->asForm();
        $this->assertNotSame($this->http, $client);
    }

    public function testPutRequest(): void
    {
        $promise = $this->http->put('https://httpbin.org/put', ['data' => 'test']);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testPatchRequest(): void
    {
        $promise = $this->http->patch('https://httpbin.org/patch', ['data' => 'test']);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testDeleteRequest(): void
    {
        $promise = $this->http->delete('https://httpbin.org/delete');
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testHeadRequest(): void
    {
        $promise = $this->http->head('https://httpbin.org/get');
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testOptionsRequest(): void
    {
        $promise = $this->http->options('https://httpbin.org/get');
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testWithMiddlewares(): void
    {
        $middleware1 = function (array $request, callable $next) {
            return $next($request);
        };
        $middleware2 = function (array $request, callable $next) {
            return $next($request);
        };

        $client = $this->http->withMiddlewares([$middleware1, $middleware2]);
        $this->assertNotSame($this->http, $client);
    }

    public function testHasPending(): void
    {
        $this->assertFalse($this->http->hasPending());
    }

    public function testGetOptions(): void
    {
        $options = $this->http->getOptions();
        $this->assertInstanceOf(RequestOptions::class, $options);
    }

    public function testClone(): void
    {
        $client = $this->http->withBaseUrl('https://api.example.com');
        $this->assertNotSame($this->http, $client);
        
        // Original should remain unchanged
        $this->assertEquals('', $this->http->getOptions()->baseUrl);
        $this->assertEquals('https://api.example.com', $client->getOptions()->baseUrl);
    }

    public function testMultipleMiddlewareChain(): void
    {
        $middleware1 = function (array $request, callable $next) {
            $request['custom1'] = true;
            return $next($request);
        };

        $middleware2 = function (array $request, callable $next) {
            $request['custom2'] = true;
            return $next($request);
        };

        $client = $this->http
            ->withMiddleware($middleware1)
            ->withMiddleware($middleware2);

        $this->assertNotSame($this->http, $client);
    }
}
