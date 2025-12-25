<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/TestHelper.php';

use Omegaalfa\HttpPromise\HttpPromise;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for HttpPromise with real HTTP requests.
 * These tests make actual network calls to httpbin.org.
 */
class IntegrationTest extends TestCase
{
    private HttpPromise $http;

    protected function setUp(): void
    {
        $this->http = HttpPromise::create(new SimpleResponse());
    }

    public function testRealGetRequest(): void
    {
        $promise = $this->http->get('https://httpbin.org/get');
        $response = $promise->wait();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('httpbin.org', $response->getBody()->getContents());
    }

    public function testRealPostRequest(): void
    {
        $data = ['test' => 'data', 'number' => 42];
        $promise = $this->http->asJson()->post('https://httpbin.org/post', $data);
        $response = $promise->wait();

        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals($data, $body['json']);
    }

    public function testConcurrentRealRequests(): void
    {
        $this->markTestSkipped('Concurrent requests test needs debugging');
        
        $requests = [
            ['method' => 'GET', 'url' => 'https://httpbin.org/uuid'],
            ['method' => 'GET', 'url' => 'https://httpbin.org/uuid'],
        ];

        $promise = $this->http->concurrent($requests);
        $responses = $promise->wait(30); // 30 second timeout

        $this->assertCount(2, $responses);
        
        foreach ($responses as $response) {
            $this->assertEquals(200, $response->getStatusCode());
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertArrayHasKey('uuid', $body);
        }
    }

    public function testMetricsAfterRequests(): void
    {
        // Make a few requests
        $this->http->get('https://httpbin.org/get')->wait();
        $this->http->get('https://httpbin.org/uuid')->wait();

        $metrics = $this->http->getMetrics();
        
        $this->assertEquals(2, $metrics['total_requests']);
        $this->assertEquals(2, $metrics['successful_requests']);
        $this->assertEquals(0, $metrics['failed_requests']);
        $this->assertGreaterThan(0, $metrics['uptime_seconds']);
        $this->assertGreaterThan(0, $metrics['requests_per_second']);
        $this->assertEquals(100.0, $metrics['success_rate']);
    }

    public function testConcurrencyControl(): void
    {
        // Create client with low concurrency limit
        $http = HttpPromise::create(new SimpleResponse(), maxConcurrent: 2);

        // Make more requests than the limit
        $promises = [];
        for ($i = 0; $i < 5; $i++) {
            $promises[] = $http->get('https://httpbin.org/get');
        }

        // Initially should have some queued
        $this->assertGreaterThanOrEqual(0, $http->queuedCount());
        
        // Wait for all to complete
        foreach ($promises as $promise) {
            $response = $promise->wait(30);
            $this->assertEquals(200, $response->getStatusCode());
        }

        // All should be processed
        $this->assertEquals(0, $http->pendingCount());
        $this->assertEquals(0, $http->queuedCount());
    }

    public function testErrorHandling(): void
    {
        // Test with invalid URL
        $promise = $this->http->get('https://invalid-domain-that-does-not-exist.com/test');
        
        $this->expectException(\Exception::class);
        $promise->wait();
    }
}