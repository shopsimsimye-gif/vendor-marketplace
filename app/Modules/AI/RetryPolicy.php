<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

class RetryPolicy
{
    private int $maxAttempts;
    private float $baseDelaySeconds;

    public function __construct(int $maxAttempts = 3, float $baseDelaySeconds = 1.0)
    {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->baseDelaySeconds = max(0.1, $baseDelaySeconds);
    }

    public function shouldRetry(int $attempt): bool
    {
        return $attempt < $this->maxAttempts;
    }

    public function nextDelay(int $attempt): int
    {
        // Exponential backoff with jitter in seconds
        $expo = pow(2, max(0, $attempt - 1));
        $delay = $this->baseDelaySeconds * $expo;
        // Add small jitter
        $jitter = rand(0, 1000) / 1000.0;
        return (int) ceil($delay + $jitter);
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }
}
