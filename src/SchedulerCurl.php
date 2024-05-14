<?php

declare(strict_types=1);

namespace omegaalfa\HttpPromise;

use CurlHandle;
use CurlMultiHandle;
use Exception;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;


class SchedulerCurl
{
	/**
	 * @var CurlMultiHandle
	 */
	protected CurlMultiHandle $curlMultiHandle;


	/**
	 * @var array<int|string, mixed>
	 */
	private array $headers;

	/**
	 * @var ResponseInterface
	 */
	private ResponseInterface $response;


	/**
	 * @NamedArgumentConstructor
	 */
	public function __construct(ResponseInterface $response)
	{
		$this->curlMultiHandle = curl_multi_init();
		$this->response = $response;
	}


	/**
	 * @param  CurlHandle  $curlHandle
	 *
	 * @return void
	 */
	private function addMultiHandler(CurlHandle $curlHandle): void
	{
		curl_multi_add_handle($this->curlMultiHandle, $curlHandle);
	}

	/**
	 * @param  string                                         $method
	 * @param  string                                         $url
	 * @param  array<int|string, bool|float|int|string|null>  $headers
	 * @param  mixed|null                                     $params
	 *
	 * @return $this
	 */
	public function request(string $method, string $url, array $headers, mixed $params = null): static
	{
		$ch = curl_init($url);
		if(!$ch instanceof CurlHandle) {
			throw new RuntimeException('Error: $ch is not instanceof CurlHandle');
		}
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
		curl_setopt($ch, CURLOPT_ENCODING, 'utf-8');
		curl_setopt($ch, CURLOPT_PROXYPORT, "80");
		curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getProcessHeaders($headers));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getProcessParams($params, $headers));
		curl_setopt($ch, CURLOPT_NOSIGNAL, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$this->addMultiHandler($ch);
		curl_close($ch);

		return $this;
	}


	/**
	 * @param  callable  $done
	 * @param  int       $usleep
	 *
	 * @return void
	 * @throws Exception
	 */
	public function then(callable $done, int $usleep = 20000): void
	{
		$active = 0;
		do {
			$mrc = curl_multi_exec($this->curlMultiHandle, $active);
			curl_multi_select($this->curlMultiHandle);
		} while($mrc === CURLM_CALL_MULTI_PERFORM);

		if($mrc !== CURLM_OK) {
			throw new RuntimeException('Curl error: ' . curl_multi_strerror($mrc));
		}

		$this->waitForCompletion(function() use ($done) {
			$response = $this->response;
			$response->getBody()->rewind();
			while($info = curl_multi_info_read($this->curlMultiHandle)) {
				$curlHandle = $info['handle'];
				if($code = (int)curl_getinfo($curlHandle, CURLINFO_HTTP_CODE)) {
					$response = $response->withStatus($code);
				}
				if($content = curl_multi_getcontent($curlHandle)) {
					$response->getBody()->write($content);
				}
				curl_multi_remove_handle($this->curlMultiHandle, $curlHandle);
				curl_close($curlHandle);
			}
			$response->getBody()->rewind();
			$done($response);
		}, $active, $usleep);
	}

	/**
	 * @param  callable  $done
	 * @param  int       $active
	 * @param  int       $usleep
	 *
	 * @return void
	 * @throws Exception
	 */
	private function waitForCompletion(callable $done, int $active, int $usleep): void
	{
		if($active === 0) {
			$done();
			return;
		}

		usleep($usleep);
		$this->then($done);
	}


	/**
	 * @param  array<int|string,  bool|float|int|string|null>  $headers
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
	 * @param  mixed                     $params
	 * @param  array<int|string, mixed>  $headers
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
				throw new RuntimeException('Error de parametro json: ' . $e->getMessage());
			}
		}

		return $data;
	}


	/**
	 *
	 */
	public function __destruct()
	{
		curl_multi_close($this->curlMultiHandle);
	}
}

