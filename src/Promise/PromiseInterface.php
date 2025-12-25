<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Promise;

use Throwable;

/**
 * Promise/A+ like interface for asynchronous operations.
 *
 * @template T The type of the resolved value
 */
interface PromiseInterface
{
    public const string STATE_PENDING = 'pending';
    public const string STATE_FULFILLED = 'fulfilled';
    public const string STATE_REJECTED = 'rejected';

    /**
     * Attaches callbacks for the resolution and/or rejection of the Promise.
     *
     * @template TResult
     * @param (callable(T): TResult)|null $onFulfilled Callback for resolution
     * @param (callable(Throwable): TResult)|null $onRejected Callback for rejection
     * @return PromiseInterface<TResult>
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface;

    /**
     * Attaches a callback for only the rejection of the Promise.
     *
     * @template TResult
     * @param callable(Throwable): TResult $onRejected
     * @return PromiseInterface<T|TResult>
     */
    public function catch(callable $onRejected): PromiseInterface;

    /**
     * Attaches a callback that is invoked when the Promise is settled (fulfilled or rejected).
     *
     * @param callable(): void $onFinally
     * @return PromiseInterface<T>
     */
    public function finally(callable $onFinally): PromiseInterface;

    /**
     * Returns the current state of the promise.
     *
     * @return string One of STATE_PENDING, STATE_FULFILLED, or STATE_REJECTED
     */
    public function getState(): string;

    /**
     * Blocks until the promise is resolved or rejected.
     * Returns the resolved value or throws the rejection reason.
     *
     * @param float|null $timeout Maximum time to wait in seconds (null = no timeout)
     * @return T The resolved value
     * @throws Throwable If the promise is rejected or the timeout is exceeded
     */
    public function wait(?float $timeout = null): mixed;

    /**
     * Returns whether the promise is pending.
     *
     * @return bool
     */
    public function isPending(): bool;

    /**
     * Returns whether the promise is fulfilled.
     *
     * @return bool
     */
    public function isFulfilled(): bool;

    /**
     * Returns whether the promise is rejected.
     *
     * @return bool
     */
    public function isRejected(): bool;
}