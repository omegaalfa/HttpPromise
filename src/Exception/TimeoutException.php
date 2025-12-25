<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Exception;

/**
 * Exception thrown when a promise operation times out.
 */
class TimeoutException extends PromiseException
{
    /**
     * @param string $message
     * @param float $timeout
     */
    public function __construct(
        string $message = 'Promise wait timeout exceeded',
        float  $timeout = 0.0
    )
    {
        parent::__construct($timeout > 0 ? sprintf('%s (timeout: %.2fs)', $message, $timeout) : $message);
    }
}
