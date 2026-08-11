<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

use VMP\Support\Cache\Manager as CacheManager;

/**
 * Circuit Breaker with half-open state and exponential backoff.
 */
class CircuitBreaker
{
    private string $prefix = 'vmp_cb_';

    // Configurable thresholds
    private int $failureThreshold;
    private int $timeoutSeconds;
    private int $halfOpenMaxCalls;

    public function __construct(
        int $failureThreshold = 5,
        int $timeoutSeconds = 60,
        int $halfOpenMaxCalls = 3
    ) {
        $this->failureThreshold = $failureThreshold;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->halfOpenMaxCalls = $halfOpenMaxCalls;
    }

    /**
     * Get current state for a provider.
     */
    public function getState(string $provider): \VMP\Modules\AI\CircuitBreakerState
    {
        $state = CacheManager::get($this->key('state', $provider));

        // لا يوجد كاش بعد، أو قيمة غير صالحة => افتراضياً CLOSED
        if ($state === null || $state === false || $state === '') {
            return new CircuitBreakerState(CircuitBreakerState::CLOSED);
        }

        if ($state === 'open') {
            $openedAt = (int) CacheManager::get($this->key('opened_at', $provider));
            if ((time() - $openedAt) >= $this->timeoutSeconds) {
                // Timeout expired — transition to half-open
                $this->transitionTo($provider, new CircuitBreakerState(CircuitBreakerState::HALF_OPEN));
                return new CircuitBreakerState(CircuitBreakerState::HALF_OPEN);
            }
            return new CircuitBreakerState(CircuitBreakerState::OPEN);
        }

        return CircuitBreakerState::from($state);
    }

    /**
     * Check if request is allowed through.
     */
    public function allowRequest(string $provider): bool
    {
        $state = $this->getState($provider)->value();

        if ($state === CircuitBreakerState::CLOSED) {
            return true;
        }

        if ($state === CircuitBreakerState::OPEN) {
            return false;
        }

        // HALF_OPEN — allow limited test calls
        $testCalls = (int) CacheManager::get($this->key('test_calls', $provider));
        return $testCalls < $this->halfOpenMaxCalls;
    }

    /**
     * Record a successful call.
     */
    public function recordSuccess(string $provider): void
    {
        $state = $this->getState($provider)->value();

        if ($state === CircuitBreakerState::HALF_OPEN) {
            $successes = (int) CacheManager::get($this->key('test_successes', $provider)) + 1;
            CacheManager::set($this->key('test_successes', $provider), $successes, $this->timeoutSeconds);

            if ($successes >= $this->halfOpenMaxCalls) {
                $this->transitionTo($provider, new CircuitBreakerState(CircuitBreakerState::CLOSED));
            }
        }

        // Reset failure count in closed state
        CacheManager::delete($this->key('failures', $provider));
    }

    /**
     * Record a failed call.
     */
    public function recordFailure(string $provider): void
    {
        $state = $this->getState($provider)->value();

        if ($state === CircuitBreakerState::HALF_OPEN) {
            // Any failure in half-open immediately trips back to open
            $this->transitionTo($provider, new CircuitBreakerState(CircuitBreakerState::OPEN));
            return;
        }

        $failures = (int) CacheManager::get($this->key('failures', $provider)) + 1;
        CacheManager::set($this->key('failures', $provider), $failures, $this->timeoutSeconds * 2);

        if ($failures >= $this->failureThreshold) {
            $this->transitionTo($provider, new CircuitBreakerState(CircuitBreakerState::OPEN));
        }
    }

    /**
     * Transition to a new state.
     */
    private function transitionTo(string $provider, CircuitBreakerState $newState): void
    {
        CacheManager::set($this->key('state', $provider), $newState->value(), $this->timeoutSeconds * 2);

        if ($newState->value() === CircuitBreakerState::OPEN) {
            CacheManager::set($this->key('opened_at', $provider), time(), $this->timeoutSeconds * 2);
        } elseif ($newState->value() === CircuitBreakerState::HALF_OPEN) {
            CacheManager::set($this->key('test_calls', $provider), 0, $this->timeoutSeconds);
            CacheManager::set($this->key('test_successes', $provider), 0, $this->timeoutSeconds);
        } elseif ($newState->value() === CircuitBreakerState::CLOSED) {
            CacheManager::delete($this->key('failures', $provider));
            CacheManager::delete($this->key('opened_at', $provider));
            CacheManager::delete($this->key('test_calls', $provider));
            CacheManager::delete($this->key('test_successes', $provider));
        }
    }

    private function key(string $type, string $provider): string
    {
        return $this->prefix . $type . '_' . md5($provider);
    }
}
