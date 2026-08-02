<?php
namespace VMP\Modules\AI\Exceptions;

defined('ABSPATH') || exit;

/**
 * Exception thrown when a workflow step should be retried later.
 * The worker will requeue the job with exponential backoff.
 */
class RetryLaterException extends \RuntimeException
{
    private int $delaySeconds;
    private int $maxRetries;
    private int $attemptNumber;

    public function __construct(
        string $message = '',
        int $delaySeconds = 60,
        int $maxRetries = 3,
        int $attemptNumber = 1,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->delaySeconds = max(1, $delaySeconds);
        $this->maxRetries = max(1, $maxRetries);
        $this->attemptNumber = max(1, $attemptNumber);
    }

    public function getDelaySeconds(): int
    {
        return $this->delaySeconds;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    /**
     * Calculate exponential backoff delay with jitter.
     */
    public function getBackoffDelay(): int
    {
        $base = $this->delaySeconds * (2 ** ($this->attemptNumber - 1));
        $jitter = random_int(0, (int) ($base * 0.2)); // 20% jitter
        return min($base + $jitter, 3600); // Max 1 hour
    }

    public function shouldRetry(): bool
    {
        return $this->attemptNumber < $this->maxRetries;
    }
}
