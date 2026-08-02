<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

/**
 * Provider Failover with Circuit Breaker, Health Scoring, and Exponential Backoff.
 */
class ProviderFailover
{
    private CapabilityRegistry $registry;
    private ProviderHealth $health;
    private CircuitBreaker $circuitBreaker;
    private ProviderHealthScore $healthScore;
    private RetryPolicy $retryPolicy;

    public function __construct(
        CapabilityRegistry $registry,
        ProviderHealth $health,
        CircuitBreaker $circuitBreaker,
        ProviderHealthScore $healthScore,
        RetryPolicy $retryPolicy
    ) {
        $this->registry = $registry;
        $this->health = $health;
        $this->circuitBreaker = $circuitBreaker;
        $this->healthScore = $healthScore;
        $this->retryPolicy = $retryPolicy;
    }

    /**
     * Resolve the best provider for a capability.
     *
     * @param string $capability
     * @return string Provider identifier
     * @throws \RuntimeException if no provider is available
     */
    public function resolve(string $capability): string
    {
        $providers = $this->registry->providersFor($capability);

        if (empty($providers)) {
            throw new \RuntimeException(sprintf('No providers configured for capability: %s', $capability));
        }

        // Score and filter providers
        $candidates = [];
        foreach ($providers as $provider) {
            // 1. Check circuit breaker
            if (!$this->circuitBreaker->allowRequest($provider)) {
                continue;
            }

            // 2. Check basic health
            if (!$this->health->isHealthy($provider)) {
                continue;
            }

            // 3. Calculate composite score
            $score = $this->healthScore->score($provider);
            $candidates[$provider] = $score;
        }

        if (empty($candidates)) {
            // All providers tripped — fallback to first with warning
            $fallback = $providers[0];
            error_log(sprintf(
                'VMP: All providers tripped for %s. Fallback to %s (circuit: %s)',
                $capability,
                $fallback,
                $this->circuitBreaker->getState($fallback)->value
            ));
            return $fallback;
        }

        // Sort by score descending
        arsort($candidates);
        return array_key_first($candidates);
    }

    /**
     * Execute a call with retry and circuit breaker tracking.
     *
     * @param string   $provider
     * @param callable $operation
     * @return mixed
     * @throws \Throwable
     */
    public function execute(string $provider, callable $operation)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryPolicy->maxAttempts()) {
            $attempt++;
            $startTime = microtime(true);

            try {
                $result = $operation();
                $latency = (microtime(true) - $startTime) * 1000;

                // Record success
                $this->circuitBreaker->recordSuccess($provider);
                $this->healthScore->record($provider, true, $latency);

                return $result;
            } catch (\VMP\Modules\AI\Exceptions\RetryLaterException $e) {
                $lastException = $e;
                $this->healthScore->record($provider, false, (microtime(true) - $startTime) * 1000);

                if (!$e->shouldRetry() || $attempt >= $e->getMaxRetries()) {
                    throw $e;
                }

                // Exponential backoff with jitter
                $delay = $e->getBackoffDelay();
                usleep($delay * 1000); // Convert to microseconds
            } catch (\Throwable $e) {
                $lastException = $e;
                $latency = (microtime(true) - $startTime) * 1000;

                // Record failure
                $this->circuitBreaker->recordFailure($provider);
                $this->healthScore->record($provider, false, $latency);

                if ($attempt >= $this->retryPolicy->maxAttempts()) {
                    break;
                }

                // Standard retry delay
                $delay = $this->retryPolicy->delay($attempt);
                usleep($delay * 1000);
            }
        }

        throw new \RuntimeException(
            sprintf('Provider %s failed after %d attempts: %s', $provider, $attempt, $lastException->getMessage()),
            0,
            $lastException
        );
    }
}
