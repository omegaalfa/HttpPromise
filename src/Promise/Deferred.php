<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Promise;

use Throwable;

/**
 * A Deferred represents a computation that may be resolved or rejected externally.
 *
 * Unlike a Promise where the executor runs immediately, a Deferred allows you
 * to resolve or reject the promise at any time from outside.
 *
 * @template T The type of the resolved value
 */
final class Deferred
{
    /** @var Promise<T> */
    private Promise $promise;

    /** @var callable(T): void */
    private $resolveCallback;

    /** @var callable(Throwable): void */
    private $rejectCallback;

    /** @var callable|null */
    private mixed $waitFn = null;

    /**
     * @param callable|null $waitFn Optional function to call when wait() is invoked on the promise
     */
    public function __construct(?callable $waitFn = null)
    {
        $this->waitFn = $waitFn;
        $this->promise = new Promise(
            function (callable $resolve, callable $reject): void {
                $this->resolveCallback = $resolve;
                $this->rejectCallback = $reject;
            },
            $this->waitFn
        );
    }

    /**
     * Returns the promise associated with this deferred.
     *
     * @return PromiseInterface<T>
     */
    public function promise(): PromiseInterface
    {
        return $this->promise;
    }

    /**
     * Resolves the promise with a value.
     *
     * @param T $value
     */
    public function resolve(mixed $value = null): void
    {
        ($this->resolveCallback)($value);
    }

    /**
     * Rejects the promise with a reason.
     *
     * @param Throwable $reason
     * @return void
     */
    public function reject(Throwable $reason): void
    {
        ($this->rejectCallback)($reason);
    }

    /**
     * Returns whether the promise is still pending.
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->promise->isPending();
    }

    /**
     * Returns whether the promise is fulfilled.
     * @return bool
     */
    public function isFulfilled(): bool
    {
        return $this->promise->isFulfilled();
    }

    /**
     * Returns whether the promise is rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->promise->isRejected();
    }
}