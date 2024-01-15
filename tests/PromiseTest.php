<?php

use PHPUnit\Framework\TestCase;
use omegaalfa\HttpPromise\Promise;

class PromiseTest extends TestCase
{
	public function testPromiseIsPendingAfterCreation()
	{
		$promise = new Promise(function() {});

		$this->assertEquals('pending', $promise->getState());
	}

	public function testPromiseIsFulfilled()
	{
		$promise = new Promise(function($resolve, $reject) {
			$resolve(10);
		});

		$promise->then(function($value) {
			$this->assertEquals(10, $value);
		});

		$this->assertEquals('fulfilled', $promise->getState());
	}

	public function testPromiseIsRejected()
	{
		$promise = new Promise(function($resolve, $reject) {
			$reject('error');
		});

		$promise->then(null, function($reason) {
			$this->assertEquals('error', $reason);
		});

		$this->assertEquals('rejected', $promise->getState());
	}

	public function testPromiseCanBeChained()
	{
		$promise1 = new Promise(function($resolve, $reject) {
			$resolve(10);
		});

		$promise2 = $promise1->then(function($value) {
			return $value * 2;
		});

		$promise2->then(function($value) {
			$this->assertEquals(20, $value);
		});

		$this->assertEquals('pending', $promise2->getState());
	}
}
