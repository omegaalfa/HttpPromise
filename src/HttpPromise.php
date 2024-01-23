<?php

declare(strict_types=1);

namespace omegaalfa\HttpPromise;

use Exception;
use Laminas\Diactoros\Response;
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
	 * @var Response
	 */
	protected Response $response;
	/**
	 * @var array
	 */
	private array $headers;

	/**
	 * construct
	 */
	public function __construct()
	{
		$this->response = new Response();
	}


	/**
	 * @param  string      $method
	 * @param  string      $url
	 * @param  mixed|null  $params
	 * @param  array       $headers
	 *
	 * @return Promise
	 */
	public function request(string $method, string $url, mixed $params = null, array $headers = []): Promise
	{
		if(!$this->validMethod($method)) {
			throw  new \InvalidArgumentException('METHOD REQUEST INVALID');
		}

		try {
			$promise = new Promise(function($resolve, $reject) use ($method, $url, $params, $headers) {
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_ENCODING, 'utf-8');
				curl_setopt($ch, CURLOPT_PROXYPORT, "80");
				curl_setopt($ch, CURLOPT_CERTINFO, true);
				curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getProcessHeaders($headers));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getProcessParams($params, $headers));

				if(is_string($content = curl_exec($ch))) {
					$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$response = $this->response->withStatus($code ?? 200);
					$response->getBody()->write($content);
					$response->getBody()->rewind();
				}
				$error = curl_error($ch);
				curl_close($ch);

				if(isset($response)) {
					$resolve($response);
				}
				if($error) {
					$reject($error);
				}
			});
		} catch(Exception $e) {
			$promise->reject($e->getMessage());
		}

		return $promise;
	}

	/**
	 * @param  string  $url
	 * @param  array   $headers
	 *
	 * @return Promise
	 */
	public function get(string $url, array $headers = []): Promise
	{
		try {
			$promise = new Promise(function($resolve, $reject) use ($url, $headers) {
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
				curl_setopt($ch, CURLOPT_ENCODING, 'utf-8');
				curl_setopt($ch, CURLOPT_PROXYPORT, "80");
				curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getProcessHeaders($headers));
				if(is_string($content = curl_exec($ch))) {
					$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$response = $this->response->withStatus($code ?? 200);
					$response->getBody()->write($content);
					$response->getBody()->rewind();
				}
				$error = curl_error($ch);
				curl_close($ch);

				if(isset($response)) {
					$resolve($response);
				}
				if($error) {
					$reject($error);
				}
			});
		} catch(Exception $e) {
			$promise->reject($e->getMessage());
		}

		return $promise;
	}

	/**
	 * @param  string      $url
	 * @param  mixed|null  $params
	 * @param  array       $headers
	 *
	 * @return Promise
	 */
	public function post(string $url, mixed $params = null, array $headers = []): Promise
	{
		return $this->request('POST', $url, $params, $headers);
	}

	/**
	 * @param  string      $url
	 * @param  mixed|null  $params
	 * @param  array       $headers
	 *
	 * @return Promise
	 */
	public function put(string $url, mixed $params = null, array $headers = []): Promise
	{
		return $this->request('PUT', $url, $params, $headers);
	}


	/**
	 * @param  string      $url
	 * @param  mixed|null  $params
	 * @param  array       $headers
	 *
	 * @return Promise
	 */
	public function delete(string $url, mixed $params = null, array $headers = []): Promise
	{
		return $this->request('DELETE', $url, $params, $headers);
	}


	/**
	 * @param  mixed       $params
	 * @param  array|null  $headers
	 *
	 * @return bool|mixed|string
	 */
	private function getProcessParams(mixed $params = '', array $headers = null): mixed
	{
		$data = $params;

		if(!$headers) {
			$headers['Content-Type'] = 'text/html; charset=utf-8';
		}

		if(is_array($params) || is_object($params)) {
			$data = urldecode(http_build_query($data));
		}

		if($params && isset($headers['Content-Type']) && str_contains($headers['Content-Type'], 'json')) {
			$data = $this->toJson($params);
		}

		return $data;
	}


	/**
	 * @param  mixed  $params
	 *
	 * @return false|string
	 */
	private function toJson(mixed $params): bool|string
	{
		try {
			return json_encode($params, JSON_THROW_ON_ERROR);
		} catch(\JsonException $e) {
			return urldecode(http_build_query(['error' => $e->getMessage()]));
		}
	}

	/**
	 * @param  array  $headers
	 *
	 * @return array
	 */
	private function getProcessHeaders(array $headers): array
	{
		if(empty($headers)) {
			$this->headers = $headers;
		}

		if($headers) {
			foreach($headers as $name => $value) {
				$this->headers[] = sprintf('%s: %s', $name, $value);
			}
		}

		return $this->headers;
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
}
