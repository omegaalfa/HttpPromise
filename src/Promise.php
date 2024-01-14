<?php

declare(strict_types=1);

namespace omegaalfa\HttpPromise;

use Exception;
use Fiber;
use RuntimeException;

/**
 * Classe que representa uma promessa de valor que pode ser resolvida ou rejeitada em um momento futuro.
 */
final class Promise
{
	/**
	 * @var Fiber
	 */
	private Fiber $fiber;

	/**
	 * @var mixed|null
	 */
	private mixed $onFulfilled;

	/**
	 * @var mixed|null
	 */
	private mixed $onRejected;

	/**
	 * @var mixed
	 */
	private mixed $result;

	/**
	 * @var string
	 */
	private string $state;

	/**
	 * Cria uma nova instância de Promise.
	 *
	 * @param  callable  $executor  Função executora que recebe duas funções como argumentos: resolve e reject.
	 *
	 * @throws Exception Se ocorrer um erro durante a execução da função executora.
	 */
	public function __construct(callable $executor)
	{
		try {
			$this->state = 'pending';
			$this->onFulfilled = null;
			$this->onRejected = null;

			$resolve = function($value) {
				if($this->state === 'pending') {
					$this->state = 'fulfilled';
					$this->result = $value;

					if(is_callable($this->onFulfilled)) {
						call_user_func($this->onFulfilled, $this->result);
					}
				}
			};

			$reject = function($reason) {
				if($this->state === 'pending') {
					$this->state = 'rejected';
					$this->result = $reason;

					if(is_callable($this->onRejected)) {
						call_user_func($this->onRejected, $this->result);
					}
				}
			};

			$this->fiber = new Fiber(function() use ($executor, $resolve, $reject) {
				$executor($resolve, $reject);
				Fiber::suspend();
			});

			$this->fiber->start();
		} catch(\Throwable $e) {
			throw new RuntimeException('Erro ao executar a função executora.', 0);
		}
	}

	/**
	 * @param  callable|null  $onFulfilled
	 * @param  callable|null  $onRejected
	 *
	 * @return Promise
	 * @throws Exception
	 */
	public function then(callable $onFulfilled = null, callable $onRejected = null): Promise
	{
		return new self(function($resolve, $reject) use ($onFulfilled, $onRejected) {
			$onFulfilled = $onFulfilled ?? static function($value) use ($resolve) {
					$resolve($value);
				};
			$onRejected = $onRejected ?? static function($reason) use ($reject) {
					$reject($reason);
				};

			if($this->state === 'fulfilled') {
				$onFulfilled($this->result);
			} elseif($this->state === 'rejected') {
				$onRejected($this->result);
			} else {
				$this->onFulfilled = $onFulfilled;
				$this->onRejected = $onRejected;
			}

			$this->fiber->resume();
		});
	}

	/**
	 * Resolve a promessa com um valor.
	 *
	 * @param  mixed  $value  O valor a ser resolvido.
	 *
	 * @throws Exception Se a promessa já foi resolvida ou rejeitada.
	 */
	public function resolve(mixed $value): void
	{
		if($this->state === 'pending') {
			$this->state = 'fulfilled';
			$this->result = $value;

			if(is_callable($this->onFulfilled)) {
				call_user_func($this->onFulfilled, $this->result);
			}
		} else {
			throw new RuntimeException('A promessa já foi resolvida ou rejeitada.');
		}
	}


	/**
	 * Rejeita a promessa com um motivo.
	 *
	 * @param  mixed  $reason  O motivo da rejeição.
	 *
	 * @throws Exception Se a promessa já foi resolvida ou rejeitada.
	 */
	public function reject(mixed $reason): void
	{
		if($this->state === 'pending') {
			$this->state = 'rejected';
			$this->result = $reason;

			if(is_callable($this->onRejected)) {
				call_user_func($this->onRejected, $this->result);
			}
		} else {
			throw new RuntimeException('A promessa já foi resolvida ou rejeitada.');
		}
	}


	/**
	 * @return string
	 */
	public function getState(): string
	{
		return $this->state;
	}
}
