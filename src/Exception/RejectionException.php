<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Exception;

use Throwable;

/**
 * Exception wrapper for non-Throwable rejection reasons.
 */
class RejectionException extends PromiseException
{
    /**
     * @param mixed $reason
     * @param Throwable|null $previous
     */
    public function __construct(
        private readonly mixed $reason,
        ?Throwable             $previous = null
    )
    {
        $message = $this->formatReason($reason);
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param mixed $reason
     * @return string
     */
    private function formatReason(mixed $reason): string
    {
        if (is_string($reason)) {
            return $reason;
        }

        if (is_array($reason)) {
            return 'Promise rejected with array';
        }

        if (is_object($reason)) {
            return sprintf('Promise rejected with %s', get_class($reason));
        }

        return sprintf('Promise rejected with %s', get_debug_type($reason));
    }

    /**
     * @return mixed
     */
    public function getReason(): mixed
    {
        return $this->reason;
    }
}
