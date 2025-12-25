<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\HttpPromise\Exception\HttpException;
use Omegaalfa\HttpPromise\Exception\PromiseException;
use Omegaalfa\HttpPromise\Exception\RejectionException;
use Omegaalfa\HttpPromise\Exception\TimeoutException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Exception classes.
 */
class ExceptionTest extends TestCase
{
    public function testHttpExceptionBasic(): void
    {
        $exception = new HttpException(
            message: 'Test error',
            url: 'https://example.com/api',
            method: 'GET',
            statusCode: 404
        );

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals('https://example.com/api', $exception->getUrl());
        $this->assertEquals('GET', $exception->getMethod());
        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertNull($exception->getResponse());
    }

    public function testHttpExceptionFromCurlError(): void
    {
        $exception = HttpException::fromCurlError(
            'Connection timeout',
            'https://example.com',
            'POST'
        );

        $this->assertStringContainsString('cURL error', $exception->getMessage());
        $this->assertStringContainsString('Connection timeout', $exception->getMessage());
        $this->assertEquals('https://example.com', $exception->getUrl());
        $this->assertEquals('POST', $exception->getMethod());
        $this->assertNull($exception->getStatusCode());
    }

    public function testHttpExceptionFromResponse(): void
    {
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getReasonPhrase')->willReturn('Internal Server Error');

        $exception = HttpException::fromResponse(
            $response,
            'https://api.example.com/endpoint',
            'PUT'
        );

        $this->assertStringContainsString('HTTP 500 error', $exception->getMessage());
        $this->assertStringContainsString('Internal Server Error', $exception->getMessage());
        $this->assertEquals('https://api.example.com/endpoint', $exception->getUrl());
        $this->assertEquals('PUT', $exception->getMethod());
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertSame($response, $exception->getResponse());
    }

    public function testPromiseException(): void
    {
        $exception = new PromiseException('Promise error');
        $this->assertEquals('Promise error', $exception->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testRejectionException(): void
    {
        $reason = new \Exception('Rejection reason');
        $exception = new RejectionException($reason);

        $this->assertStringContainsString('Promise rejected', $exception->getMessage());
        $this->assertSame($reason, $exception->getReason());
    }

    public function testRejectionExceptionWithStringReason(): void
    {
        $exception = new RejectionException('Simple rejection');
        $this->assertEquals('Simple rejection', $exception->getReason());
    }

    public function testTimeoutException(): void
    {
        $exception = new TimeoutException('Request timed out after 30s');
        $this->assertEquals('Request timed out after 30s', $exception->getMessage());
        $this->assertInstanceOf(PromiseException::class, $exception);
    }

    public function testHttpExceptionWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new HttpException(
            message: 'HTTP error',
            url: 'https://test.com',
            method: 'DELETE',
            previous: $previous
        );

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testHttpExceptionWithCode(): void
    {
        $exception = new HttpException(
            message: 'Error with code',
            url: 'https://test.com',
            method: 'GET',
            code: 123
        );

        $this->assertEquals(123, $exception->getCode());
    }
}
