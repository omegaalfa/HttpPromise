<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\HttpPromise\Exception\TimeoutException;
use Omegaalfa\HttpPromise\Promise\Promise;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Promise implementation.
 */
class PromiseTest extends TestCase
{
    public function testCreatePromise(): void
    {
        $promise = new Promise(function ($resolve, $reject) {
            // Do nothing
        });
        $this->assertInstanceOf(Promise::class, $promise);
    }

    public function testPromiseResolve(): void
    {
        $promise = new Promise(function ($resolve, $reject) {
            $resolve('success');
        });

        $this->assertEquals('fulfilled', $promise->getState());
        $this->assertEquals('success', $promise->wait());
    }

    public function testPromiseReject(): void
    {
        $promise = new Promise(function ($resolve, $reject) {
            $reject(new \Exception('error'));
        });

        $this->expectException(\Exception::class);
        $promise->wait();
    }

    public function testPromiseThen(): void
    {
        $promise = new Promise(function ($resolve, $reject) {
            $resolve('test');
        });

        $result = null;
        $promise->then(function ($value) use (&$result) {
            $result = $value . ' processed';
        });

        $this->assertEquals('test processed', $result);
    }

    public function testPromiseCatch(): void
    {
        $promise = new Promise(function ($resolve, $reject) {
            $reject(new \Exception('test error'));
        });

        $error = null;
        $promise->catch(function ($e) use (&$error) {
            $error = $e->getMessage();
        });

        $this->assertEquals('test error', $error);
    }

    public function testPromiseAll(): void
    {
        $promise1 = new Promise(function ($resolve, $reject) {
            $resolve('a');
        });
        $promise2 = new Promise(function ($resolve, $reject) {
            $resolve('b');
        });

        $all = Promise::all([$promise1, $promise2]);

        $result = $all->wait();
        $this->assertEquals(['a', 'b'], $result);
    }

    public function testPromiseRace(): void
    {
        $promise1 = new Promise(function ($resolve, $reject) {
            $resolve('first');
        });
        $promise2 = new Promise(function ($resolve, $reject) {
            // Never resolves
        });

        $race = Promise::race([$promise1, $promise2]);

        $result = $race->wait();
        $this->assertEquals('first', $result);
    }

    public function testPromiseResolveWithPromise(): void
    {
        $innerPromise = new Promise(function ($resolve) {
            $resolve('inner value');
        });

        $promise = Promise::resolve($innerPromise);
        
        $this->assertSame($innerPromise, $promise);
        $this->assertEquals('inner value', $promise->wait());
    }

    public function testPromiseResolveWithValue(): void
    {
        $promise = Promise::resolve('direct value');
        
        $this->assertEquals('fulfilled', $promise->getState());
        $this->assertEquals('direct value', $promise->wait());
    }

    public function testPromiseRejectStatic(): void
    {
        $error = new \RuntimeException('rejection error');
        $promise = Promise::reject($error);
        
        $this->assertEquals('rejected', $promise->getState());
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rejection error');
        $promise->wait();
    }

    public function testPromiseFinally(): void
    {
        $finallyCalled = false;
        
        $promise = new Promise(function ($resolve) {
            $resolve('success');
        });

        $promise->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        });

        $promise->wait();
        $this->assertTrue($finallyCalled);
    }

    public function testPromiseFinallyOnRejection(): void
    {
        $finallyCalled = false;
        
        $promise = new Promise(function ($resolve, $reject) {
            $reject(new \Exception('error'));
        });

        $promise->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        });

        try {
            $promise->wait();
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertTrue($finallyCalled);
    }



    public function testPromiseAllEmpty(): void
    {
        $promise = Promise::all([]);
        $result = $promise->wait();
        
        $this->assertEquals([], $result);
    }

    public function testPromiseAllWithRejection(): void
    {
        $promise1 = Promise::resolve('a');
        $promise2 = Promise::reject(new \Exception('error'));
        $promise3 = Promise::resolve('c');

        $all = Promise::all([$promise1, $promise2, $promise3]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error');
        $all->wait();
    }

    public function testPromiseAllSettled(): void
    {
        $promise1 = Promise::resolve('success');
        $promise2 = Promise::reject(new \Exception('error'));
        $promise3 = Promise::resolve('another success');

        $allSettled = Promise::allSettled([$promise1, $promise2, $promise3]);
        $results = $allSettled->wait();

        $this->assertCount(3, $results);
        $this->assertEquals('fulfilled', $results[0]['status']);
        $this->assertEquals('success', $results[0]['value']);
        $this->assertEquals('rejected', $results[1]['status']);
        $this->assertInstanceOf(\Exception::class, $results[1]['reason']);
        $this->assertEquals('fulfilled', $results[2]['status']);
    }

    public function testPromiseAny(): void
    {
        $promise1 = Promise::reject(new \Exception('error1'));
        $promise2 = Promise::resolve('success');
        $promise3 = Promise::reject(new \Exception('error2'));

        $any = Promise::any([$promise1, $promise2, $promise3]);
        $result = $any->wait();

        $this->assertEquals('success', $result);
    }

    public function testPromiseAnyAllRejected(): void
    {
        $promise1 = Promise::reject(new \Exception('error1'));
        $promise2 = Promise::reject(new \Exception('error2'));

        $any = Promise::any([$promise1, $promise2]);

        $this->expectException(\Exception::class);
        $any->wait();
    }



    public function testPromiseWaitWithTimeout(): void
    {
        $promise = new Promise(function ($resolve, $reject) {
            // Never resolves
        });

        $this->expectException(TimeoutException::class);
        $promise->wait(0.1);
    }

    public function testPromiseGetState(): void
    {
        $pendingPromise = new Promise(function ($resolve, $reject) {
            // Pending
        });
        $this->assertEquals('pending', $pendingPromise->getState());

        $fulfilledPromise = Promise::resolve('value');
        $this->assertEquals('fulfilled', $fulfilledPromise->getState());

        $rejectedPromise = Promise::reject(new \Exception('error'));
        $this->assertEquals('rejected', $rejectedPromise->getState());
    }

    public function testPromiseThenReturnsNewPromise(): void
    {
        $promise = Promise::resolve('value');
        $newPromise = $promise->then(function ($value) {
            return $value . ' modified';
        });

        $this->assertNotSame($promise, $newPromise);
        $this->assertEquals('value modified', $newPromise->wait());
    }

    public function testPromiseCatchReturnsNewPromise(): void
    {
        $promise = Promise::reject(new \Exception('error'));
        $newPromise = $promise->catch(function ($e) {
            return 'recovered';
        });

        $this->assertNotSame($promise, $newPromise);
        $this->assertEquals('recovered', $newPromise->wait());
    }

    public function testPromiseChaining(): void
    {
        $promise = Promise::resolve(5)
            ->then(function ($value) {
                return $value * 2;
            })
            ->then(function ($value) {
                return $value + 3;
            })
            ->then(function ($value) {
                return $value . ' final';
            });

        $this->assertEquals('13 final', $promise->wait());
    }

    public function testPromiseAllWithNonPromiseValues(): void
    {
        $all = Promise::all([
            'plain value',
            Promise::resolve('promise value'),
            42
        ]);

        $result = $all->wait();
        $this->assertEquals(['plain value', 'promise value', 42], $result);
    }

    public function testPromiseRaceEmpty(): void
    {
        $this->expectException(\Exception::class);
        $race = Promise::race([]);
        $race->wait();
    }

    public function testPromiseAllSettledEmpty(): void
    {
        $promise = Promise::allSettled([]);
        $result = $promise->wait();
        
        $this->assertEquals([], $result);
    }

    public function testPromiseAnyEmpty(): void
    {
        $this->expectException(\Exception::class);
        $any = Promise::any([]);
        $any->wait();
    }

    public function testPromiseThenWithPromiseReturn(): void
    {
        $promise = Promise::resolve(5)
            ->then(function ($value) {
                return Promise::resolve($value * 2);
            });

        $this->assertEquals(10, $promise->wait());
    }

    public function testPromiseCatchWithPromiseReturn(): void
    {
        $promise = Promise::reject(new \Exception('error'))
            ->catch(function ($e) {
                return Promise::resolve('recovered');
            });

        $this->assertEquals('recovered', $promise->wait());
    }

    public function testPromiseThenWithException(): void
    {
        $promise = Promise::resolve('test')
            ->then(function ($value) {
                throw new \Exception('thrown in then');
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('thrown in then');
        $promise->wait();
    }

    public function testPromiseCatchWithException(): void
    {
        $promise = Promise::reject(new \Exception('initial'))
            ->catch(function ($e) {
                throw new \RuntimeException('thrown in catch');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('thrown in catch');
        $promise->wait();
    }

    public function testPromiseExecutorWithException(): void
    {
        $promise = new Promise(function ($resolve, $reject) {
            throw new \Exception('executor error');
        });

        $this->assertEquals('rejected', $promise->getState());
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('executor error');
        $promise->wait();
    }

    public function testPromiseWaitMultipleTimes(): void
    {
        $promise = Promise::resolve('value');
        
        $result1 = $promise->wait();
        $result2 = $promise->wait();
        
        $this->assertEquals('value', $result1);
        $this->assertEquals('value', $result2);
    }

    public function testPromiseAllPreservesKeys(): void
    {
        $promises = [
            'first' => Promise::resolve('a'),
            'second' => Promise::resolve('b'),
            'third' => Promise::resolve('c')
        ];

        $result = Promise::all($promises)->wait();

        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('second', $result);
        $this->assertArrayHasKey('third', $result);
        $this->assertEquals('a', $result['first']);
    }

    public function testPromiseThenAfterResolved(): void
    {
        $promise = Promise::resolve('value');
        
        $result = null;
        $promise->then(function ($value) use (&$result) {
            $result = $value;
        });

        $this->assertEquals('value', $result);
    }

    public function testPromiseCatchAfterRejected(): void
    {
        $promise = Promise::reject(new \Exception('error'));
        
        $error = null;
        $promise->catch(function ($e) use (&$error) {
            $error = $e->getMessage();
        });

        $this->assertEquals('error', $error);
    }
}

