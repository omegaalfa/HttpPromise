<?php

declare(strict_types=1);

namespace omegaalfa\HttpPromise;

use Exception;
use Throwable;

class Promise
{
	/**
	 * @var mixed
	 */
	private mixed $value;

	/**
	 * @var string
	 */
	private string $state = 'pending';

	/**
	 * @var array<int, callable>
	 */
	private array $onFulfilled = [];

	/**
	 * @var array<int, callable>
	 */
	private array $onRejected = [];


	/**
	 * Promise constructor.
	 *
	 * @param  callable|null  $executor
	 */
	public function __construct(callable $executor = null)
	{
		if(is_callable($executor)) {
			try {
				$executor(
					fn($value) => $this->resolve($value),
					fn($reason) => $this->reject($reason)
				);
			} catch(Throwable $e) {
				$this->reject($e);
			}
		}
	}

	/**
	 * Attaches callbacks for the resolution or rejection of the Promise.
	 *
	 * @param  callable|null  $onFulfilled
	 * @param  callable|null  $onRejected
	 *
	 * @return Promise
	 * @throws Exception
	 */
	public function then(callable $onFulfilled = null, callable $onRejected = null): Promise
	{
		return new Promise(function($resolve, $reject) use ($onFulfilled, $onRejected) {
			if($this->state === 'fulfilled') {
				$this->executeCallback($onFulfilled, $this->value, $resolve, $reject);
			} elseif($this->state === 'rejected') {
				$this->executeCallback($onRejected, $this->value, $resolve, $reject);
			} else {
				$this->onFulfilled[] = fn($value) => $this->executeCallback($onFulfilled, $value, $resolve, $reject);
				$this->onRejected[] = fn($reason) => $this->executeCallback($onRejected, $reason, $resolve, $reject);
			}
		});
	}

	/**
	 * Resolves the Promise with a value.
	 *
	 * @param  mixed  $value
	 *
	 * @return void
	 */
	public function resolve(mixed $value): void
	{
		if($this->state === 'pending') {
			$this->value = $value;
			$this->state = 'fulfilled';
			$this->executeCallbacks($this->onFulfilled, $value);
		}
	}

	/**
	 * Rejects the Promise with a reason.
	 *
	 * @param  mixed  $reason
	 *
	 * @return void
	 */
	public function reject(mixed $reason): void
	{
		if($this->state === 'pending') {
			$this->value = $reason;
			$this->state = 'rejected';
			$this->executeCallbacks($this->onRejected, $reason);
		}
	}

	/**
	 * Executes an array of callbacks with the provided value.
	 *
	 * @param  array<int, callable>  $callbacks
	 * @param  mixed                 $value
	 *
	 * @return void
	 */
	private function executeCallbacks(array $callbacks, mixed $value): void
	{
		while($callback = array_shift($callbacks)) {
			if(is_callable($callback)) {
				$this->executeCallback($callback, $value);
			}
		}
	}

	/**
	 * Executes a callback with the provided value, and optionally resolves/rejects a new Promise.
	 *
	 * @param  callable|null  $callback
	 * @param  mixed          $value
	 * @param  callable|null  $resolve
	 * @param  callable|null  $reject
	 *
	 * @return void
	 */
	private function executeCallback(?callable $callback, mixed $value, callable $resolve = null, callable $reject = null): void
	{
		if(is_callable($callback)) {
			try {
				$result = $callback($value);
				if($resolve) {
					$resolve($result);
				}
			} catch(Throwable $e) {
				if($reject) {
					$reject($e);
				}
			}
		}
	}

	/**
	 * Gets the current state of the Promise.
	 *
	 * @return string
	 */
	public function getState(): string
	{
		return $this->state;
	}
}
