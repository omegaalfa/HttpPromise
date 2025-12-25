<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\HttpPromise\Http\RequestOptions;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for RequestOptions class.
 */
class RequestOptionsTest extends TestCase
{
    public function testCreateDefaultOptions(): void
    {
        $options = RequestOptions::create();

        $this->assertEquals('', $options->baseUrl);
        $this->assertEquals(30.0, $options->timeout);
        $this->assertEquals(30.0, $options->readTimeout);
        $this->assertTrue($options->followRedirects);
        $this->assertEquals(5, $options->maxRedirects);
        $this->assertTrue($options->verifySSL);
        $this->assertNull($options->userAgent);
        $this->assertNull($options->proxy);
        $this->assertEquals([], $options->defaultHeaders);
        $this->assertEquals(0, $options->retryAttempts);
        $this->assertEquals(1.0, $options->retryDelay);
        $this->assertEquals([429, 502, 503, 504], $options->retryStatusCodes);
        $this->assertFalse($options->http2);
        $this->assertTrue($options->tcpKeepAlive);
    }

    public function testWithBaseUrl(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withBaseUrl('https://api.example.com');

        $this->assertNotSame($options, $newOptions);
        $this->assertEquals('https://api.example.com', $newOptions->baseUrl);
        $this->assertEquals('', $options->baseUrl);
    }

    public function testWithBaseUrlTrimsSlash(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withBaseUrl('https://api.example.com/');

        $this->assertEquals('https://api.example.com', $newOptions->baseUrl);
    }

    public function testWithTimeout(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withTimeout(60.0);

        $this->assertNotSame($options, $newOptions);
        $this->assertEquals(60.0, $newOptions->timeout);
        $this->assertEquals(30.0, $options->timeout);
    }

    public function testWithReadTimeout(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withReadTimeout(45.0);

        $this->assertNotSame($options, $newOptions);
        $this->assertEquals(45.0, $newOptions->readTimeout);
    }

    public function testWithRetry(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withRetry(3, 2.5, [500, 502, 503]);

        $this->assertNotSame($options, $newOptions);
        $this->assertEquals(3, $newOptions->retryAttempts);
        $this->assertEquals(2.5, $newOptions->retryDelay);
        $this->assertEquals([500, 502, 503], $newOptions->retryStatusCodes);
    }

    public function testWithMaxRedirects(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withMaxRedirects(10);

        $this->assertNotSame($options, $newOptions);
        $this->assertEquals(10, $newOptions->maxRedirects);
    }

    public function testWithoutSSLVerification(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withoutSSLVerification();

        $this->assertNotSame($options, $newOptions);
        $this->assertFalse($newOptions->verifySSL);
    }

    public function testWithUserAgent(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withUserAgent('MyApp/1.0');

        $this->assertNotSame($options, $newOptions);
        $this->assertEquals('MyApp/1.0', $newOptions->userAgent);
    }

    public function testWithProxy(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withProxy('http://proxy.example.com:8080');

        $this->assertNotSame($options, $newOptions);
        $this->assertEquals('http://proxy.example.com:8080', $newOptions->proxy);
    }

    public function testWithHeaders(): void
    {
        $options = RequestOptions::create();
        $headers = ['X-Custom' => 'value'];
        $newOptions = $options->withHeaders($headers);

        $this->assertNotSame($options, $newOptions);
        $this->assertEquals($headers, $newOptions->defaultHeaders);
    }

    public function testWithHeadersMerge(): void
    {
        $options = RequestOptions::create()->withHeaders(['X-First' => 'value1']);
        $newOptions = $options->withHeaders(['X-Second' => 'value2']);

        $this->assertEquals([
            'X-First' => 'value1',
            'X-Second' => 'value2'
        ], $newOptions->defaultHeaders);
    }

    public function testWithoutRedirects(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withoutRedirects();

        $this->assertNotSame($options, $newOptions);
        $this->assertFalse($newOptions->followRedirects);
        $this->assertTrue($options->followRedirects);
    }

    public function testWithHttp2(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withHttp2(true);

        $this->assertNotSame($options, $newOptions);
        $this->assertTrue($newOptions->http2);
        $this->assertFalse($options->http2);
    }

    public function testWithTcpKeepAlive(): void
    {
        $options = RequestOptions::create();
        $newOptions = $options->withTcpKeepAlive(false);

        $this->assertNotSame($options, $newOptions);
        $this->assertFalse($newOptions->tcpKeepAlive);
        $this->assertTrue($options->tcpKeepAlive);
    }

    public function testImmutability(): void
    {
        $options = RequestOptions::create();
        $modified = $options
            ->withBaseUrl('https://api.test.com')
            ->withTimeout(60.0)
            ->withHttp2(true);

        // Original should be unchanged
        $this->assertEquals('', $options->baseUrl);
        $this->assertEquals(30.0, $options->timeout);
        $this->assertFalse($options->http2);

        // New instance should have changes
        $this->assertEquals('https://api.test.com', $modified->baseUrl);
        $this->assertEquals(60.0, $modified->timeout);
        $this->assertTrue($modified->http2);
    }

    public function testChainedOperations(): void
    {
        $options = RequestOptions::create()
            ->withBaseUrl('https://api.example.com')
            ->withTimeout(45.0)
            ->withHeaders(['Authorization' => 'Bearer token'])
            ->withRetry(3)
            ->withHttp2(true);

        $this->assertEquals('https://api.example.com', $options->baseUrl);
        $this->assertEquals(45.0, $options->timeout);
        $this->assertEquals(['Authorization' => 'Bearer token'], $options->defaultHeaders);
        $this->assertEquals(3, $options->retryAttempts);
        $this->assertTrue($options->http2);
    }

    public function testGetUserAgent(): void
    {
        $options = RequestOptions::create();
        $userAgent = $options->getUserAgent();
        
        $this->assertStringContainsString('HttpPromise', $userAgent);
        $this->assertStringContainsString('PHP/', $userAgent);
    }

    public function testBuildFullUrl(): void
    {
        $options = RequestOptions::create()->withBaseUrl('https://api.example.com');
        
        $this->assertEquals('https://api.example.com/users', $options->buildFullUrl('/users'));
        $this->assertEquals('https://api.example.com/users', $options->buildFullUrl('users'));
        $this->assertEquals('https://other.com/path', $options->buildFullUrl('https://other.com/path'));
    }

    public function testWithBearerToken(): void
    {
        $options = RequestOptions::create()->withBearerToken('my-token');
        
        $this->assertArrayHasKey('Authorization', $options->defaultHeaders);
        $this->assertEquals('Bearer my-token', $options->defaultHeaders['Authorization']);
    }

    public function testWithBasicAuth(): void
    {
        $options = RequestOptions::create()->withBasicAuth('user', 'pass');
        
        $this->assertArrayHasKey('Authorization', $options->defaultHeaders);
        $this->assertStringStartsWith('Basic ', $options->defaultHeaders['Authorization']);
    }

    public function testAsJson(): void
    {
        $options = RequestOptions::create()->asJson();
        
        $this->assertEquals('application/json', $options->defaultHeaders['Content-Type']);
        $this->assertEquals('application/json', $options->defaultHeaders['Accept']);
    }

    public function testAsForm(): void
    {
        $options = RequestOptions::create()->asForm();
        
        $this->assertEquals('application/x-www-form-urlencoded', $options->defaultHeaders['Content-Type']);
    }
}
