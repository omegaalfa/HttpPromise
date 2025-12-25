<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\HttpPromise\Promise\Deferred;
use Omegaalfa\HttpPromise\Promise\Promise;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Deferred class.
 */
class DeferredTest extends TestCase
{
    public function testCreateDeferred(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $this->assertInstanceOf(Promise::class, $promise);
        $this->assertEquals('pending', $promise->getState());
    }

    public function testResolveDeferred(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $deferred->resolve('test value');

        $this->assertEquals('fulfilled', $promise->getState());
        $this->assertEquals('test value', $promise->wait());
    }

    public function testRejectDeferred(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $exception = new \Exception('test error');
        $deferred->reject($exception);

        $this->assertEquals('rejected', $promise->getState());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test error');
        $promise->wait();
    }

    public function testDeferredThen(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $result = null;
        $promise->then(function ($value) use (&$result) {
            $result = $value;
        });

        $deferred->resolve('success');

        $this->assertEquals('success', $result);
    }

    public function testDeferredCatch(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $error = null;
        $promise->catch(function ($e) use (&$error) {
            $error = $e->getMessage();
        });

        $deferred->reject(new \Exception('failure'));

        $this->assertEquals('failure', $error);
    }

    public function testDeferredChaining(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $chainedPromise = $promise->then(function ($value) {
            return $value * 2;
        });

        $deferred->resolve(5);

        $this->assertEquals(10, $chainedPromise->wait());
    }

    public function testMultipleThenHandlers(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $results = [];
        $promise->then(function ($value) use (&$results) {
            $results[] = 'handler1: ' . $value;
        });
        $promise->then(function ($value) use (&$results) {
            $results[] = 'handler2: ' . $value;
        });

        $deferred->resolve('test');

        $this->assertCount(2, $results);
        $this->assertContains('handler1: test', $results);
        $this->assertContains('handler2: test', $results);
    }

    public function testDeferredStateTransition(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $this->assertEquals('pending', $promise->getState());

        $deferred->resolve('done');
        $this->assertEquals('fulfilled', $promise->getState());
    }

    public function testDeferredRejectionStateTransition(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $this->assertEquals('pending', $promise->getState());

        $deferred->reject(new \Exception('error'));
        $this->assertEquals('rejected', $promise->getState());
    }
}
