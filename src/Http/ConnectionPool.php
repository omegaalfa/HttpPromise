<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Http;

use CurlHandle;
use RuntimeException;

/**
 * Manages a pool of cURL handles for connection reuse and performance optimization.
 * 
 * Maintains separate pools per host for better connection management.
 * 
 * @phpstan-type HostPoolMap array<string, list<CurlHandle>>
 */
final class ConnectionPool
{
    /** @var HostPoolMap Pool of idle handles organized by host */
    private array $pool = [];

    /**
     * @param int $maxPoolSize Maximum number of idle handles to keep per host
     */
    public function __construct(private int $maxPoolSize = 50)
    {
    }

    /**
     * Acquires a cURL handle from the pool or creates a new one.
     *
     * @param string $url URL to extract host from
     * @return CurlHandle Ready to use cURL handle
     * @throws RuntimeException If cURL handle creation fails
     */
    public function acquire(string $url): CurlHandle
    {
        $host = parse_url($url, PHP_URL_HOST) ?? 'default';

        if (isset($this->pool[$host]) && !empty($this->pool[$host])) {
            $handle = array_pop($this->pool[$host]);
            if ($handle instanceof CurlHandle) {
                $this->resetHandle($handle);
                return $handle;
            }
        }

        $handle = curl_init();
        if (!$handle instanceof CurlHandle) {
            throw new RuntimeException('Failed to create cURL handle');
        }
        return $handle;
    }

    /**
     * Releases a cURL handle back to the pool for reuse.
     *
     * @param CurlHandle $handle Handle to release
     * @param string $url URL to extract host from
     * @return void
     */
    public function release(CurlHandle $handle, string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST) ?? 'default';

        if (!isset($this->pool[$host])) {
            $this->pool[$host] = [];
        }

        if (count($this->pool[$host]) >= $this->maxPoolSize) {
            curl_close($handle);
            return;
        }

        $this->pool[$host][] = $handle;
    }

    /**
     * Resets a cURL handle to clean state, removing sensitive data.
     *
     * @param CurlHandle $handle Handle to reset
     * @return void
     */
    private function resetHandle(CurlHandle $handle): void
    {
        // Clear headers explicitly before reset
        curl_setopt($handle, CURLOPT_HTTPHEADER, []);

        if (function_exists('curl_reset')) {
            // curl_reset clears all options including USERPWD, COOKIE, POSTFIELDS
            curl_reset($handle);
        }
    }

    /**
     * Closes all pooled handles and clears the pool.
     *
     * @return void
     */
    public function closeAll(): void
    {
        foreach ($this->pool as $handles) {
            foreach ($handles as $handle) {
                if ($handle instanceof CurlHandle) {
                    curl_close($handle);
                }
            }
        }
        $this->pool = [];
    }

    /**
     * Updates the maximum pool size and trims existing pools if necessary.
     *
     * @param int $maxPoolSize New maximum pool size
     * @return void
     */
    public function setMaxPoolSize(int $maxPoolSize): void
    {
        $this->maxPoolSize = max(0, $maxPoolSize);

        if ($this->maxPoolSize === 0) {
            $this->closeAll();
            return;
        }

        // Trim pools that exceed new limit
        foreach ($this->pool as $host => $handles) {
            while (count($handles) > $this->maxPoolSize) {
                $handle = array_pop($handles);
                if ($handle instanceof CurlHandle) {
                    curl_close($handle);
                }
            }
            $this->pool[$host] = $handles;
        }
    }

    /**
     * Returns the current pool statistics.
     *
     * @return array{total_handles: int, hosts: int, max_pool_size: int}
     */
    public function getStats(): array
    {
        $totalHandles = array_sum(array_map('count', $this->pool));

        return [
            'total_handles' => $totalHandles,
            'hosts' => count($this->pool),
            'max_pool_size' => $this->maxPoolSize,
        ];
    }
}
