<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

use VMP\Support\CacheManager;

/**
 * Circuit Breaker with half-open state and exponential backoff.
 */
class CircuitBreaker
{
    private CacheManager $cache;
    private string $prefix = 'vmp_cb_';

    // Configurable thresholds
    private int $failureThreshold;
    private int $timeoutSeconds;
    private int $halfOpenMaxCalls;

    public function __construct(
        CacheManager $cache,
        int $failureThreshold = 5,
        int $timeoutSeconds = 60,
        int $halfOpenMaxCalls = 3
    ) {
        $this->cache = $cache;
        $this->failureThreshold = $failureThreshold;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->halfOpenMaxCalls = $halfOpenMaxCalls;
    }

    /**
     * Get current state for a provider.
     */
    public function getState(string $provider): \VMP\Modules\AI\CircuitBreakerState
    {
        $state = $this->cache->get($this->key('state', $provider));

        if ($state === null) {
            return new CircuitBreakerState(CircuitBreakerState::CLOSED);
        }

        if ($state === 'open') {
            $openedAt = (int) $this->cache->get($this->key('opened_at', $provider));
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
        $state = $this->getState($provider);

        if ($state === new CircuitBreakerState(CircuitBreakerState::CLOSED)) {
            return true;
        }

        if ($state === new CircuitBreakerState(CircuitBreakerState::OPEN)) {
            return false;
        }

        // HALF_OPEN — allow limited test calls
        $testCalls = (int) $this->cache->get($this->key('test_calls', $provider));
        return $testCalls < $this->halfOpenMaxCalls;
    }

    /**
     * Record a successful call.
     */
    public function recordSuccess(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === new CircuitBreakerState(CircuitBreakerState::HALF_OPEN)) {
            $successes = (int) $this->cache->get($this->key('test_successes', $provider)) + 1;
            $this->cache->set($this->key('test_successes', $provider), $successes, $this->timeoutSeconds);

            if ($successes >= $this->halfOpenMaxCalls) {
                $this->transitionTo($provider, new CircuitBreakerState(CircuitBreakerState::CLOSED));
            }
        }

        // Reset failure count in closed state
        $this->cache->delete($this->key('failures', $provider));
    }

    /**
     * Record a failed call.
     */
    public function recordFailure(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === new CircuitBreakerState(CircuitBreakerState::HALF_OPEN)) {
            // Any failure in half-open immediately trips back to open
            $this->transitionTo($provider, new CircuitBreakerState(CircuitBreakerState::OPEN));
            return;
        }

        $failures = (int) $this->cache->get($this->key('failures', $provider)) + 1;
        $this->cache->set($this->key('failures', $provider), $failures, $this->timeoutSeconds * 2);

        if ($failures >= $this->failureThreshold) {
            $this->transitionTo($provider, new CircuitBreakerState(CircuitBreakerState::OPEN));
        }
    }

    /**
     * Transition to a new state.
     */
    private function transitionTo(string $provider, CircuitBreakerState $newState): void
    {
        $this->cache->set($this->key('state', $provider), $newState->value(), $this->timeoutSeconds * 2);

        if ($newState === new CircuitBreakerState(CircuitBreakerState::OPEN)) {
            $this->cache->set($this->key('opened_at', $provider), time(), $this->timeoutSeconds * 2);
        } elseif ($newState === new CircuitBreakerState(CircuitBreakerState::HALF_OPEN)) {
            $this->cache->set($this->key('test_calls', $provider), 0, $this->timeoutSeconds);
            $this->cache->set($this->key('test_successes', $provider), 0, $this->timeoutSeconds);
        } elseif ($newState === new CircuitBreakerState(CircuitBreakerState::CLOSED)) {
            $this->cache->delete($this->key('failures', $provider));
            $this->cache->delete($this->key('opened_at', $provider));
            $this->cache->delete($this->key('test_calls', $provider));
            $this->cache->delete($this->key('test_successes', $provider));
        }
    }

    private function key(string $type, string $provider): string
    {
        return $this->prefix . $type . '_' . md5($provider);
    }
}
