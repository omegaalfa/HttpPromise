<?php

declare(strict_types=1);

namespace omegaalfa\HttpPromise;


use Psr\Http\Message\ResponseInterface;
use omegaalfa\HttpPromise\SchedulerCurl;

/**
 * Class ClientHttp
 *
 * @package src\http
 */
class ClientHttp extends SchedulerCurl
{

	/**
	 * @var ClientHttp|null
	 */
	protected static ?ClientHttp $instance = null;


	/**
	 * @param  ResponseInterface  $response
	 *
	 * @return ClientHttp
	 */
	public static function API(ResponseInterface $response): ClientHttp
	{
		if(self::$instance === null) {
			self::$instance = new self($response);
		}

		return self::$instance;
	}

	/**
	 * @param  string                                         $url
	 * @param  array<int|string, bool|float|int|string|null>  $headers
	 *
	 * @return SchedulerCurl
	 */
	public function get(string $url, array $headers = []): SchedulerCurl
	{
		return $this->request('GET', $url, headers: $headers);
	}


	/**
	 * @param  string                                         $url
	 * @param  array<int|string, bool|float|int|string|null>  $headers
	 * @param  mixed                                          $params
	 *
	 * @return $this
	 */
	public function put(string $url, array $headers = [], mixed $params = null): static
	{
		return $this->request('PUT', $url, headers: $headers, params: $params);
	}

	/**
	 * @param  string                                         $url
	 * @param  array<int|string, bool|float|int|string|null>  $headers
	 * @param  mixed                                          $params
	 *
	 * @return $this
	 */
	public function post(string $url, mixed $headers = [], mixed $params = null): static
	{
		return $this->request('POST', $url, headers: $headers, params: $params);
	}

	/**
	 * @param  string                                         $url
	 * @param  array<int|string, bool|float|int|string|null>  $headers
	 * @param  mixed                                          $params
	 *
	 * @return $this
	 */
	public function delete(string $url, array $headers = [], mixed $params = null): static
	{
		return $this->request('DELETE', $url, headers: $headers, params: $params);
	}
}
