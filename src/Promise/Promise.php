<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Promise;

use Omegaalfa\HttpPromise\Exception\RejectionException;
use Omegaalfa\HttpPromise\Exception\TimeoutException;
use Throwable;

/**
 * A Promise/A+ like implementation for PHP.
 *
 * @template T The type of the resolved value
 * @implements PromiseInterface<T>
 */
final class Promise implements PromiseInterface
{
    protected string $state = self::STATE_PENDING;

    /** @var T|Throwable|null */
    protected mixed $result = null;

    /** @var array<array{callable|null, callable|null, callable, callable}> */
    protected array $handlers = [];

    /** @var callable|null */
    protected $waitFn = null;

    /** @var callable|null */
    protected mixed $cancelFn = null;

    /**
     * Creates a new Promise.
     *
     * @param callable(callable(T): void, callable(Throwable): void): void $executor
     * @param callable|null $waitFn Optional function to call when wait() is invoked
     * @param callable|null $cancelFn Optional function to call when cancel() is invoked
     */
    public function __construct(
        callable  $executor,
        ?callable $waitFn = null,
        ?callable $cancelFn = null
    )
    {
        $this->waitFn = $waitFn;
        $this->cancelFn = $cancelFn;

        try {
            $executor(
                function (mixed $value): void {
                    $this->doResolve($value);
                },
                function (Throwable $reason): void {
                    $this->doReject($reason);
                }
            );
        } catch (Throwable $e) {
            $this->doReject($e);
        }
    }

    /**
     * Resolves the promise with a value.
     *
     * @param T|PromiseInterface<T> $value
     */
    private function doResolve(mixed $value): void
    {
        if ($this->state !== self::STATE_PENDING) {
            return;
        }

        if ($value instanceof PromiseInterface) {
            $value->then(
                function (mixed $v): void {
                    $this->doResolve($v);
                },
                function (Throwable $e): void {
                    $this->doReject($e);
                }
            );
            return;
        }

        $this->state = self::STATE_FULFILLED;
        $this->result = $value;
        $this->processHandlers();
    }

    /**
     * Attaches callbacks for the resolution and/or rejection of the Promise.
     * 
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @return PromiseInterface<mixed>
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        return new self(
            function (callable $resolve, callable $reject) use ($onFulfilled, $onRejected): void {
                $handler = function () use ($resolve, $reject, $onFulfilled, $onRejected): void {
                    try {
                        if ($this->state === self::STATE_FULFILLED) {
                            /** @var T $fulfilledResult */
                            $fulfilledResult = $this->result;
                            $result = $onFulfilled !== null
                                ? $onFulfilled($fulfilledResult)
                                : $fulfilledResult;
                        } else {
                            /** @var Throwable $rejectedResult */
                            $rejectedResult = $this->result instanceof Throwable
                                ? $this->result
                                : new RejectionException($this->result);
                            if ($onRejected !== null) {
                                $result = $onRejected($rejectedResult);
                            } else {
                                $reject($rejectedResult);
                                return;
                            }
                        }

                        if ($result instanceof PromiseInterface) {
                            $result->then($resolve, $reject);
                        } else {
                            $resolve($result);
                        }
                    } catch (Throwable $e) {
                        $reject($e);
                    }
                };

                if ($this->state === self::STATE_PENDING) {
                    $this->handlers[] = [$onFulfilled, $onRejected, $resolve, $reject];
                } else {
                    $handler();
                }
            },
            $this->waitFn
        );
    }

    /**
     * Rejects the promise with a reason.
     *
     * @param Throwable $reason
     * @return void
     */
    private function doReject(Throwable $reason): void
    {
        if ($this->state !== self::STATE_PENDING) {
            return;
        }

        $this->state = self::STATE_REJECTED;
        $this->result = $reason;
        $this->processHandlers();
    }

    /**
     * Process all pending handlers.
     *
     * @return void
     */
    private function processHandlers(): void
    {
        foreach ($this->handlers as [$onFulfilled, $onRejected, $resolve, $reject]) {
            try {
                if ($this->state === self::STATE_FULFILLED) {
                    $result = $onFulfilled !== null
                        ? $onFulfilled($this->result)
                        : $this->result;

                    if ($result instanceof PromiseInterface) {
                        $result->then($resolve, $reject);
                    } else {
                        $resolve($result);
                    }
                } else {
                    if ($onRejected !== null) {
                        $result = $onRejected($this->result);

                        if ($result instanceof PromiseInterface) {
                            $result->then($resolve, $reject);
                        } else {
                            $resolve($result);
                        }
                    } else {
                        $reject($this->result);
                    }
                }
            } catch (Throwable $e) {
                $reject($e);
            }
        }

        $this->handlers = [];
    }

    /**
     * Returns a promise that resolves when all promises resolve.
     * Rejects immediately if any promise rejects.
     *
     * @template TValue
     * @param iterable<PromiseInterface<TValue>|TValue> $promises
     * @return PromiseInterface<array<TValue>>
     */
    public static function all(iterable $promises): PromiseInterface
    {
        $promisesArray = is_array($promises) ? $promises : iterator_to_array($promises);

        if (empty($promisesArray)) {
            return self::resolve([]);
        }

        return new self(function (callable $resolve, callable $reject) use ($promisesArray): void {
            $results = [];
            $remaining = count($promisesArray);
            $rejected = false;

            foreach ($promisesArray as $index => $promise) {
                if ($rejected) {
                    break;
                }

                $promise = $promise instanceof PromiseInterface
                    ? $promise
                    : self::resolve($promise);

                $promise->then(
                    function (mixed $value) use ($index, &$results, &$remaining, $resolve, &$rejected): void {
                        if ($rejected) {
                            return;
                        }
                        $results[$index] = $value;
                        if (--$remaining === 0) {
                            // Preserve original keys (string or numeric)
                            $resolve($results);
                        }
                    },
                    function (Throwable $reason) use ($reject, &$rejected): void {
                        if (!$rejected) {
                            $rejected = true;
                            $reject($reason);
                        }
                    }
                );
            }
        });
    }

    /**
     * Creates a promise that is already resolved with the given value.
     *
     * @template TValue
     * @param TValue $value
     * @return PromiseInterface<TValue>
     */
    public static function resolve(mixed $value): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }

        return new self(function (callable $resolve) use ($value): void {
            $resolve($value);
        });
    }

    /**
     * Returns a promise that resolves/rejects with the first promise that settles.
     *
     * @template TValue
     * @param iterable<PromiseInterface<TValue>|TValue> $promises
     * @return PromiseInterface<TValue>
     */
    public static function race(iterable $promises): PromiseInterface
    {
        return new self(function (callable $resolve, callable $reject) use ($promises): void {
            $settled = false;

            foreach ($promises as $promise) {
                if ($settled) {
                    break;
                }

                $promise = $promise instanceof PromiseInterface
                    ? $promise
                    : self::resolve($promise);

                $promise->then(
                    function (mixed $value) use ($resolve, &$settled): void {
                        if (!$settled) {
                            $settled = true;
                            $resolve($value);
                        }
                    },
                    function (Throwable $reason) use ($reject, &$settled): void {
                        if (!$settled) {
                            $settled = true;
                            $reject($reason);
                        }
                    }
                );
            }
        });
    }

    /**
     * Returns a promise that resolves with the first fulfilled promise.
     * Rejects only if all promises reject.
     *
     * @template TValue
     * @param iterable<PromiseInterface<TValue>|TValue> $promises
     * @return PromiseInterface<TValue>
     */
    public static function any(iterable $promises): PromiseInterface
    {
        $promisesArray = is_array($promises) ? $promises : iterator_to_array($promises);

        if (empty($promisesArray)) {
            return self::reject(new RejectionException('No promises provided'));
        }

        return new self(function (callable $resolve, callable $reject) use ($promisesArray): void {
            $errors = [];
            $remaining = count($promisesArray);
            $resolved = false;

            foreach ($promisesArray as $index => $promise) {
                $promise = $promise instanceof PromiseInterface
                    ? $promise
                    : self::resolve($promise);

                $promise->then(
                    function (mixed $value) use ($resolve, &$resolved): void {
                        if (!$resolved) {
                            $resolved = true;
                            $resolve($value);
                        }
                    },
                    function (Throwable $reason) use ($index, &$errors, &$remaining, $reject, &$resolved): void {
                        if ($resolved) {
                            return;
                        }
                        $errors[$index] = $reason;
                        if (--$remaining === 0) {
                            $reject(new RejectionException(
                                sprintf('All %d promises were rejected', count($errors))
                            ));
                        }
                    }
                );
            }
        });
    }

    /**
     * Creates a promise that is already rejected with the given reason.
     *
     * @param Throwable $reason
     * @return PromiseInterface<never>
     */
    public static function reject(Throwable $reason): PromiseInterface
    {
        return new self(function (callable $resolve, callable $reject) use ($reason): void {
            $reject($reason);
        });
    }

    /**
     * Returns a promise that resolves when all promises are settled.
     * Never rejects.
     *
     * @template TValue
     * @param iterable<PromiseInterface<TValue>|TValue> $promises
     * @return PromiseInterface<array<array{status: string, value?: TValue, reason?: Throwable}>>
     */
    public static function allSettled(iterable $promises): PromiseInterface
    {
        $promisesArray = is_array($promises) ? $promises : iterator_to_array($promises);

        if (empty($promisesArray)) {
            return self::resolve([]);
        }

        return new self(function (callable $resolve) use ($promisesArray): void {
            $results = [];
            $remaining = count($promisesArray);

            foreach ($promisesArray as $index => $promise) {
                $promise = $promise instanceof PromiseInterface
                    ? $promise
                    : self::resolve($promise);

                $promise->then(
                    function (mixed $value) use ($index, &$results, &$remaining, $resolve): void {
                        $results[$index] = [
                            'status' => self::STATE_FULFILLED,
                            'value' => $value,
                        ];
                        if (--$remaining === 0) {
                            ksort($results);
                            $resolve(array_values($results));
                        }
                    },
                    function (Throwable $reason) use ($index, &$results, &$remaining, $resolve): void {
                        $results[$index] = [
                            'status' => self::STATE_REJECTED,
                            'reason' => $reason,
                        ];
                        if (--$remaining === 0) {
                            ksort($results);
                            $resolve(array_values($results));
                        }
                    }
                );
            }
        });
    }

    /**
     * Creates a promise that resolves after the given delay.
     *
     * @template TValue
     * @param float $seconds Delay in seconds
     * @param TValue $value Optional value to resolve with
     * @return PromiseInterface<TValue>
     */
    public static function delay(float $seconds, mixed $value = null): PromiseInterface
    {
        return new self(function (callable $resolve) use ($seconds, $value): void {
            usleep((int)($seconds * 1_000_000));
            $resolve($value);
        });
    }

    /**
     * Wraps a callable that may throw into a promise.
     *
     * @template TValue
     * @param callable(): TValue $fn
     * @return PromiseInterface<TValue>
     */
    public static function try(callable $fn): PromiseInterface
    {
        return new self(function (callable $resolve, callable $reject) use ($fn): void {
            try {
                $resolve($fn());
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    /**
     * {@inheritdoc}
     */
    public function finally(callable $onFinally): PromiseInterface
    {
        return $this->then(
            function (mixed $value) use ($onFinally): mixed {
                $onFinally();
                return $value;
            },
            function (Throwable $reason) use ($onFinally): never {
                $onFinally();
                throw $reason;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function wait(?float $timeout = null): mixed
    {
        if ($this->state === self::STATE_FULFILLED) {
            return $this->result;
        }

        if ($this->state === self::STATE_REJECTED) {
            $this->throwRejection();
        }

        // Execute the wait function if available
        if ($this->waitFn !== null) {
            $startTime = microtime(true);

            while ($this->state === self::STATE_PENDING) {
                ($this->waitFn)();

                if ($timeout !== null) {
                    $elapsed = microtime(true) - $startTime;
                    if ($elapsed >= $timeout) {
                        throw new TimeoutException('Promise wait timeout exceeded', $timeout);
                    }
                }

                // Small sleep to prevent CPU spinning
                usleep(1000);
            }
        }

        if ($this->state === self::STATE_FULFILLED) {
            return $this->result;
        }

        if ($this->state === self::STATE_REJECTED) {
            $this->throwRejection();
        }

        throw new TimeoutException('Promise could not be resolved');
    }

    /**
     * Throws the rejection reason.
     *
     * @return never
     * @throws Throwable
     */
    private function throwRejection(): never
    {
        if ($this->result instanceof Throwable) {
            throw $this->result;
        }

        throw new RejectionException($this->result);
    }

    /**
     * {@inheritdoc}
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }

    /**
     * {@inheritdoc}
     */
    public function isFulfilled(): bool
    {
        return $this->state === self::STATE_FULFILLED;
    }

    /**
     * {@inheritdoc}
     */
    public function isRejected(): bool
    {
        return $this->state === self::STATE_REJECTED;
    }

    /**
     * Makes the Promise compatible with await($promise) patterns.
     *
     * @param callable $resolve
     * @param callable $reject
     * @return void
     */
    public function __invoke(callable $resolve, callable $reject): void
    {
        $this->then($resolve, $reject);
    }
}
