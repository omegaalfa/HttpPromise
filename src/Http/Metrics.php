<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Http;

/**
 * Tracks performance metrics for HTTP requests.
 * 
 * Provides statistics including request counts, success rates, and throughput.
 */
final class Metrics
{
    /** @var int Total number of requests initiated */
    private int $total = 0;

    /** @var int Number of successfully completed requests */
    private int $successful = 0;

    /** @var int Number of failed requests */
    private int $failed = 0;

    /** @var float Timestamp when metrics tracking started */
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Increments the total request counter.
     *
     * @return void
     */
    public function incrementTotal(): void
    {
        $this->total++;
    }

    /**
     * Increments the successful request counter.
     *
     * @return void
     */
    public function incrementSuccessful(): void
    {
        $this->successful++;
    }

    /**
     * Increments the failed request counter.
     *
     * @return void
     */
    public function incrementFailed(): void
    {
        $this->failed++;
    }

    /**
     * Returns the total number of requests.
     *
     * @return int Total requests
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Returns the number of successful requests.
     *
     * @return int Successful requests
     */
    public function getSuccessful(): int
    {
        return $this->successful;
    }

    /**
     * Returns the number of failed requests.
     *
     * @return int Failed requests
     */
    public function getFailed(): int
    {
        return $this->failed;
    }

    /**
     * Returns the uptime in seconds since metrics started.
     *
     * @return float Uptime in seconds
     */
    public function getUptime(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Calculates the success rate as a percentage.
     *
     * @return float Success rate (0-100)
     */
    public function getSuccessRate(): float
    {
        return $this->total > 0 ? ($this->successful / $this->total) * 100 : 0.0;
    }

    /**
     * Calculates requests per second throughput.
     *
     * @return float Requests per second
     */
    public function getRequestsPerSecond(): float
    {
        $uptime = $this->getUptime();
        return $uptime > 0 ? $this->total / $uptime : 0.0;
    }

    /**
     * Returns all metrics as an associative array.
     *
     * @param int $pending Number of currently pending requests
     * @param int $queued Number of queued requests
     * @return array{total_requests: int, successful_requests: int, failed_requests: int, pending_requests: int, queued_requests: int, uptime_seconds: float, requests_per_second: float, success_rate: float}
     */
    public function toArray(int $pending, int $queued): array
    {
        return [
            'total_requests' => $this->total,
            'successful_requests' => $this->successful,
            'failed_requests' => $this->failed,
            'pending_requests' => $pending,
            'queued_requests' => $queued,
            'uptime_seconds' => $this->getUptime(),
            'requests_per_second' => $this->getRequestsPerSecond(),
            'success_rate' => $this->getSuccessRate(),
        ];
    }

    /**
     * Resets all metrics to initial state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->total = 0;
        $this->successful = 0;
        $this->failed = 0;
        $this->startTime = microtime(true);
    }
}
