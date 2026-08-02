<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

use VMP\Support\CacheManager;

/**
 * Calculates a composite health score for AI providers.
 *
 * Score formula:
 *   40% success_rate
 *   30% latency_score (lower latency = higher score)
 *   20% cost_efficiency (lower cost = higher score)
 *   10% recency (recent activity bonus)
 */
class ProviderHealthScore
{
    private CacheManager $cache;
    private string $prefix = 'vmp_health_';

    // Weight configuration
    private array $weights = [
        'success_rate' => 0.40,
        'latency'      => 0.30,
        'cost'         => 0.20,
        'recency'      => 0.10,
    ];

    public function __construct(CacheManager $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Record a provider call result.
     */
    public function record(string $provider, bool $success, float $latencyMs, float $cost = 0.0): void
    {
        $key = $this->prefix . 'calls_' . md5($provider);
        $calls = $this->cache->get($key) ?: [];

        $calls[] = [
            'success'   => $success,
            'latency'   => $latencyMs,
            'cost'      => $cost,
            'timestamp' => time(),
        ];

        // Keep only last 100 calls (sliding window)
        if (count($calls) > 100) {
            $calls = array_slice($calls, -100);
        }

        $this->cache->set($key, $calls, 86400); // 24 hours
    }

    /**
     * Calculate composite health score (0.0 - 1.0).
     */
    public function score(string $provider): float
    {
        $key = $this->prefix . 'calls_' . md5($provider);
        $calls = $this->cache->get($key) ?: [];

        if (empty($calls)) {
            return 0.5; // Neutral score for unknown providers
        }

        // Use only last 50 calls for scoring
        $recent = array_slice($calls, -50);

        $successRate = $this->calculateSuccessRate($recent);
        $latencyScore = $this->calculateLatencyScore($recent);
        $costScore = $this->calculateCostScore($recent);
        $recencyScore = $this->calculateRecencyScore($recent);

        $score = (
            $successRate * $this->weights['success_rate'] +
            $latencyScore * $this->weights['latency'] +
            $costScore * $this->weights['cost'] +
            $recencyScore * $this->weights['recency']
        );

        return min(1.0, max(0.0, $score));
    }

    private function calculateSuccessRate(array $calls): float
    {
        if (empty($calls)) {
            return 0.5;
        }
        $successes = count(array_filter($calls, fn($c) => $c['success']));
        return $successes / count($calls);
    }

    private function calculateLatencyScore(array $calls): float
    {
        $latencies = array_column($calls, 'latency');
        if (empty($latencies)) {
            return 0.5;
        }
        $avgLatency = array_sum($latencies) / count($latencies);
        // Score: <100ms = 1.0, >5000ms = 0.0
        return max(0.0, 1.0 - ($avgLatency / 5000));
    }

    private function calculateCostScore(array $calls): float
    {
        $costs = array_column($calls, 'cost');
        if (empty($costs) || max($costs) === 0.0) {
            return 1.0; // Free = perfect score
        }
        $avgCost = array_sum($costs) / count($costs);
        // Score: $0 = 1.0, $0.10 = 0.0
        return max(0.0, 1.0 - ($avgCost / 0.10));
    }

    private function calculateRecencyScore(array $calls): float
    {
        if (empty($calls)) {
            return 0.0;
        }
        $lastCall = end($calls);
        $secondsAgo = time() - $lastCall['timestamp'];
        // Score: <1 hour = 1.0, >24 hours = 0.0
        return max(0.0, 1.0 - ($secondsAgo / 86400));
    }
}
