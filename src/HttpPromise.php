<?php

declare(strict_types=1);

namespace omegaalfa\HttpPromise;

use CurlHandle;
use CurlMultiHandle;
use Psr\Http\Message\ResponseInterface;
use omegaalfa\HttpPromise\Promise;


class HttpPromise
{

	/**
	 * array
	 */
	private const VALID_HTTP_METHODS = [
		'GET',
		'POST',
		'PUT',
		'DELETE',
		'HEAD',
		'OPTIONS',
		'TRACE',
		'CONNECT',
	];

	/**
	 * @var CurlMultiHandle
	 */
	private CurlMultiHandle $multiHandle;

	/**
	 * @var array<int, mixed>
	 */
	private array $handles = [];

	/**
	 * @var list<mixed>
	 */
	private array $headers;

	/**
	 * @param  ResponseInterface  $response
	 */
	public function __construct(protected ResponseInterface $response)
	{
		$this->multiHandle = curl_multi_init();
	}


	/**
	 * @param  CurlHandle   $handle
	 * @param  string       $url
	 * @param  string       $method
	 * @param  list<mixed>  $headers
	 * @param  mixed        $params
	 *
	 * @return void
	 */
	private function setCurlOptions(CurlHandle $handle, string $url, string $method, array $headers, mixed $params): void
	{
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CUSTOMREQUEST, strtoupper($method));

		if(!empty($headers)) {
			curl_setopt($handle, CURLOPT_HTTPHEADER, $this->getProcessHeaders($headers));
		}

		if((is_array($params) || is_object($params)) && !empty($params) && (strtoupper($method) === 'GET')) {
			$url .= '?' . http_build_query($params);
		}

		if((is_array($params) || is_object($params)) && !empty($params)) {
			curl_setopt($handle, CURLOPT_POSTFIELDS, $this->getProcessParams($params, $headers));
		}

		curl_setopt($handle, CURLOPT_URL, $url);
	}


	/**
	 * @param  mixed        $params
	 * @param  list<mixed>  $headers
	 *
	 * @return false|mixed|string
	 */
	private function getProcessParams(mixed $params, array $headers): mixed
	{
		$data = $params;

		if(!$headers) {
			$headers['Content-Type'] = 'text/html; charset=utf-8';
		}

		if(is_array($data) || is_object($data)) {
			$data = urldecode(http_build_query($data));
		}

		if($params
			&& isset($headers['Content-Type'])
			&& is_string($headers['Content-Type'])
			&& str_contains($headers['Content-Type'], 'json')) {
			try {
				$data = json_encode($params, JSON_THROW_ON_ERROR);
			} catch(\JsonException $e) {
				throw new \RuntimeException('Error de parametro json: ' . $e->getMessage());
			}
		}

		return $data;
	}

	/**
	 * @param  list<mixed>  $headers
	 *
	 * @return array<int|string, mixed>
	 */
	private function getProcessHeaders(array $headers): array
	{
		if(empty($headers)) {
			$this->headers = $headers;
		}

		if($headers) {
			foreach($headers as $name => $value) {
				if($value) {
					$this->headers[] = sprintf('%s: %s', $name, $value);
				}
			}
		}

		return $this->headers;
	}

	/**
	 * @param  string       $method
	 * @param  string       $url
	 * @param  list<mixed>  $headers
	 * @param  mixed        $params
	 *
	 * @return Promise
	 */
	public function request(string $method, string $url, array $headers, mixed $params): Promise
	{

		$promise = new Promise();
		$handle = curl_init($url);
		if(!$handle instanceof CurlHandle) {
			throw new \RuntimeException('Error: $ch is not instanceof CurlHandle');
		}

		$this->setCurlOptions($handle, $url, $method, $headers, $params);
		curl_multi_add_handle($this->multiHandle, $handle);

		$this->handles[(int)$handle] = [
			'handle'  => $handle,
			'promise' => $promise
		];

		return $promise;
	}

	/**
	 * @param  string       $url
	 * @param  list<mixed>  $headers
	 * @param  mixed|null   $params
	 *
	 * @return Promise
	 */
	public function get(string $url, array $headers = [], mixed $params = null): Promise
	{
		return $this->request('GET', $url, $headers, $params);
	}

	/**
	 * @param  string       $url
	 * @param  list<mixed>  $headers
	 * @param  mixed|null   $params
	 *
	 * @return Promise
	 */
	public function post(string $url, mixed $headers = [], mixed $params = null): Promise
	{
		return $this->request('POST', $url, headers: $headers, params: $params);
	}

	/**
	 * @param  string       $url
	 * @param  list<mixed>  $headers
	 * @param  mixed|null   $params
	 *
	 * @return Promise
	 */
	public function put(string $url, mixed $headers = [], mixed $params = null): Promise
	{
		return $this->request('PUT', $url, headers: $headers, params: $params);
	}

	/**
	 * @param  string       $url
	 * @param  list<mixed>  $headers
	 * @param  mixed|null   $params
	 *
	 * @return Promise
	 */
	public function delete(string $url, mixed $headers = [], mixed $params = null): Promise
	{
		return $this->request('DELETE', $url, headers: $headers, params: $params);
	}

	/**
	 * @param  string  $method
	 *
	 * @return bool
	 */
	private function validMethod(string $method): bool
	{
		if(in_array($method, self::VALID_HTTP_METHODS)) {
			return true;
		}

		return false;
	}

	/**
	 * @return void
	 */
	public function wait(): void
	{
		do {
			$status = curl_multi_exec($this->multiHandle, $active);
			curl_multi_select($this->multiHandle);
		} while($active && $status === CURLM_OK);

		while($info = curl_multi_info_read($this->multiHandle)) {
			$handle = $info['handle'];
			$handleData = $this->handles[(int)$handle];
			if(($promise = $handleData['promise']) && $promise instanceof Promise) {
				if($info['result'] === CURLE_OK) {
					$response = $this->response;
					if($code = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE)) {
						$response = $response->withStatus($code);
					}
					if($content = curl_multi_getcontent($handle)) {
						$response->getBody()->write($content);
					}
					$response->getBody()->rewind();
					$promise->resolve($response);
				} else {
					$promise->reject(curl_error($handle));
				}
			}
			curl_multi_remove_handle($this->multiHandle, $handle);
			curl_close($handle);
			unset($this->handles[(int)$handle]);
		}
	}
}
