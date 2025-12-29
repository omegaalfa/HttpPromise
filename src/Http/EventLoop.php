<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Http;

use CurlHandle;
use CurlMultiHandle;
use Omegaalfa\HttpPromise\Promise\Deferred;
use Psr\Http\Message\ResponseInterface;

/**
 * Manages the event loop for processing HTTP requests asynchronously.
 *
 * Handles queuing and processing of pending requests with concurrency control.
 *
 * @phpstan-import-type PendingRequest from \Omegaalfa\HttpPromise\HttpPromise
 * @phpstan-import-type QueuedRequest from \Omegaalfa\HttpPromise\HttpPromise
 */
final class EventLoop
{
    /**
     * @var array<int, array{handle: CurlHandle, deferred: Deferred<ResponseInterface>, url: string, method: string, attempt: int}>
     * Currently executing requests indexed by handle ID
     */
    private array $pending = [];

    /**
     * @var list<array{deferred: Deferred<ResponseInterface>, url: string, method: string, headers: array<string, string|int|float|bool|null>, body: mixed, query: array<string, mixed>|null, attempt: int, enqueuedAt: float, retryAfter?: float}>
     * Queued requests waiting for available slots
     */
    private array $queued = [];

    /**
     * @param CurlMultiHandle $multiHandle cURL multi handle for concurrent requests
     * @param int $maxConcurrent Maximum number of concurrent requests
     */
    public function __construct(private CurlMultiHandle $multiHandle, private int $maxConcurrent
    )
    {
    }

    /**
     * Adds a request to the pending queue.
     *
     * @param array{handle: CurlHandle, deferred: Deferred<ResponseInterface>, url: string, method: string, attempt: int} $data Request data
     * @return void
     */
    public function addPending(array $data): void
    {
        $handleId = spl_object_id($data['handle']);
        $this->pending[$handleId] = $data;
    }

    /**
     * Adds a request to the queued list.
     *
     * @param array{deferred: Deferred<ResponseInterface>, url: string, method: string, headers: array<string, string|int|float|bool|null>, body: mixed, query: array<string, mixed>|null, attempt: int, enqueuedAt: float, retryAfter?: float} $data Request data
     * @return void
     */
    public function addQueued(array $data): void
    {
        $this->queued[] = $data;
    }

    /**
     * Returns all pending requests.
     *
     * @return array<int, array{handle: CurlHandle, deferred: Deferred<ResponseInterface>, url: string, method: string, attempt: int}>
     */
    public function getPending(): array
    {
        return $this->pending;
    }

    /**
     * Returns all queued requests.
     *
     * @return list<array{deferred: Deferred<ResponseInterface>, url: string, method: string, headers: array<string, string|int|float|bool|null>, body: mixed, query: array<string, mixed>|null, attempt: int, enqueuedAt: float, retryAfter?: float}>
     */
    public function getQueued(): array
    {
        return $this->queued;
    }

    /**
     * Removes a request from pending by handle ID.
     *
     * @param int $handleId Handle object ID
     * @return array{handle: CurlHandle, deferred: Deferred<ResponseInterface>, url: string, method: string, attempt: int}|null Removed request data or null
     */
    public function removePending(int $handleId): ?array
    {
        $data = $this->pending[$handleId] ?? null;
        unset($this->pending[$handleId]);
        return $data;
    }

    /**
     * Removes and returns the first queued request.
     *
     * @return array{deferred: Deferred<ResponseInterface>, url: string, method: string, headers: array<string, string|int|float|bool|null>, body: mixed, query: array<string, mixed>|null, attempt: int, enqueuedAt: float, retryAfter?: float}|null First queued request or null
     */
    public function shiftQueued(): ?array
    {
        return array_shift($this->queued);
    }

    /**
     * Checks if queued requests can be processed (slots available).
     *
     * @return bool True if there are queued requests and available slots
     */
    public function canProcessQueued(): bool
    {
        return !empty($this->queued) && count($this->pending) < $this->maxConcurrent;
    }

    /**
     * Returns the number of pending requests.
     *
     * @return int Count of pending requests
     */
    public function pendingCount(): int
    {
        return count($this->pending);
    }

    /**
     * Returns the number of queued requests.
     *
     * @return int Count of queued requests
     */
    public function queuedCount(): int
    {
        return count($this->queued);
    }

    /**
     * Removes a queued request by index.
     *
     * @param int $index Index of the request to remove
     * @return void
     */
    public function removeQueued(int $index): void
    {
        unset($this->queued[$index]);
    }

    /**
     * Re-indexes the queued array after removals.
     *
     * @return void
     */
    public function reindexQueued(): void
    {
        $this->queued = array_values($this->queued);
    }

    /**
     * Executes a single tick of the event loop.
     *
     * Processes cURL multi handle until no immediate work remains.
     *
     * @return void
     */
    public function tick(): void
    {
        if (!$this->hasPending()) {
            return;
        }

        do {
            $status = curl_multi_exec($this->multiHandle, $stillRunning);
        } while ($status === CURLM_CALL_MULTI_PERFORM);
    }

    /**
     * Checks if there are any pending or queued requests.
     *
     * @return bool True if there are requests to process
     */
    public function hasPending(): bool
    {
        return !empty($this->pending) || !empty($this->queued);
    }

    /**
     * Updates the maximum concurrent requests limit.
     *
     * @param int $maxConcurrent New maximum concurrent requests
     * @return void
     */
    public function setMaxConcurrent(int $maxConcurrent): void
    {
        $this->maxConcurrent = max(1, $maxConcurrent);
    }

    /**
     * Returns the maximum concurrent requests limit.
     *
     * @return int Maximum concurrent requests
     */
    public function getMaxConcurrent(): int
    {
        return $this->maxConcurrent;
    }
}
