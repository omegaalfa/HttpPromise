<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Exception;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Exception for HTTP-related errors.
 */
class HttpException extends PromiseException
{
    /**
     * @param string $message
     * @param string|null $url
     * @param string|null $method
     * @param int|null $statusCode
     * @param ResponseInterface|null $response
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        string                              $message,
        private readonly ?string            $url = null,
        private readonly ?string            $method = null,
        private readonly ?int               $statusCode = null,
        private readonly ?ResponseInterface $response = null,
        int                                 $code = 0,
        ?Throwable                          $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Creates an exception for a cURL error.
     */
    public static function fromCurlError(string $error, string $url, string $method): self
    {
        return new self(
            message: sprintf('cURL error: %s', $error),
            url: $url,
            method: $method
        );
    }

    /**
     * Creates an exception for an HTTP error response.
     *
     * @param ResponseInterface $response
     * @param string $url
     * @param string $method
     * @return self
     */
    public static function fromResponse(ResponseInterface $response, string $url, string $method): self
    {
        return new self(
            message: sprintf(
                'HTTP %d error: %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ),
            url: $url,
            method: $method,
            statusCode: $response->getStatusCode(),
            response: $response
        );
    }

    /**
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return string|null
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
