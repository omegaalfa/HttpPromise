<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Handles retry logic for failed HTTP requests.
 * 
 * Only retries idempotent methods to prevent duplicate operations.
 */
final class RetryHandler
{
    /** @var array<string> HTTP methods safe to retry */
    private const array IDEMPOTENT_METHODS = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'];

    /**
     * @param int $maxAttempts Maximum number of retry attempts
     * @param float $delay Base delay between retries in seconds
     * @param array<int> $statusCodes HTTP status codes that trigger retry
     */
    public function __construct(
        private int $maxAttempts,
        private float $delay,
        private array $statusCodes
    ) {
    }

    /**
     * Determines if a request should be retried based on response and attempt count.
     *
     * @param ResponseInterface $response HTTP response received
     * @param string $method HTTP method used
     * @param int $attempt Current attempt number
     * @return bool True if request should be retried
     */
    public function shouldRetry(ResponseInterface $response, string $method, int $attempt): bool
    {
        if ($attempt > $this->maxAttempts) {
            return false;
        }

        // Only retry idempotent methods to prevent duplicate operations
        if (!in_array($method, self::IDEMPOTENT_METHODS, true)) {
            return false;
        }

        return in_array($response->getStatusCode(), $this->statusCodes, true);
    }

    /**
     * Determines if a network error should trigger a retry.
     *
     * @param int $attempt Current attempt number
     * @return bool True if should retry on network error
     */
    public function shouldRetryOnError(int $attempt): bool
    {
        return $attempt <= $this->maxAttempts;
    }

    /**
     * Calculates retry delay with exponential backoff.
     * 
     * Formula: delay * 2^(attempt-1)
     * Examples:
     * - Attempt 1: delay * 1
     * - Attempt 2: delay * 2
     * - Attempt 3: delay * 4
     *
     * @param int $attempt Current attempt number
     * @return float Delay in seconds before next retry
     */
    public function getRetryDelay(int $attempt): float
    {
        return $this->delay * pow(2, $attempt - 1);
    }

    /**
     * Calculates the timestamp when retry should be attempted.
     *
     * @param int $attempt Current attempt number
     * @return float Unix timestamp when retry should occur
     */
    public function getRetryAfter(int $attempt): float
    {
        return microtime(true) + $this->getRetryDelay($attempt);
    }

    /**
     * Returns the maximum number of retry attempts.
     *
     * @return int Maximum attempts
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Returns the base delay between retries.
     *
     * @return float Base delay in seconds
     */
    public function getDelay(): float
    {
        return $this->delay;
    }

    /**
     * Returns the status codes that trigger retry.
     *
     * @return array<int> HTTP status codes
     */
    public function getStatusCodes(): array
    {
        return $this->statusCodes;
    }

    /**
     * Updates retry configuration.
     *
     * @param int $maxAttempts New maximum attempts
     * @param float $delay New base delay
     * @param array<int> $statusCodes New status codes
     * @return void
     */
    public function updateConfig(int $maxAttempts, float $delay, array $statusCodes): void
    {
        $this->maxAttempts = max(0, $maxAttempts);
        $this->delay = max(0.0, $delay);
        $this->statusCodes = $statusCodes;
    }
}
