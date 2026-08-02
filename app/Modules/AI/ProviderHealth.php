<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

/**
 * Class ProviderHealth
 *
 * Tracks provider health metrics and determines whether a provider should be considered healthy.
 */
class ProviderHealth
{
    private const OPTION_KEY = 'vmp_ai_provider_health';

    private array $metrics;

    public function __construct()
    {
        $this->metrics = get_option(self::OPTION_KEY, []);
    }

    public function recordSuccess(string $provider, string $capability = ''): void
    {
        $metric = $this->metrics[$provider] ?? [
            'successes' => 0,
            'failures' => 0,
            'requests' => 0,
            'latency_ms' => 0,
            'updated_at' => current_time('mysql'),
            'capabilities' => [],
        ];

        $metric['successes']++;
        $metric['requests']++;
        $metric['updated_at'] = current_time('mysql');
        $metric['capabilities'][$capability] = ($metric['capabilities'][$capability] ?? 0) + 1;

        $this->metrics[$provider] = $metric;
        $this->save();
    }

    public function recordFailure(string $provider, string $capability = '', int $latencyMs = 0): void
    {
        $metric = $this->metrics[$provider] ?? [
            'successes' => 0,
            'failures' => 0,
            'requests' => 0,
            'latency_ms' => 0,
            'updated_at' => current_time('mysql'),
            'capabilities' => [],
        ];

        $metric['failures']++;
        $metric['requests']++;
        $metric['latency_ms'] += $latencyMs;
        $metric['updated_at'] = current_time('mysql');
        $metric['capabilities'][$capability] = ($metric['capabilities'][$capability] ?? 0) + 1;

        $this->metrics[$provider] = $metric;
        $this->save();
    }

    public function isHealthy(string $provider): bool
    {
        $metric = $this->getMetrics($provider);
        $requests = max(1, $metric['requests']);
        $failureRate = $metric['failures'] / $requests;

        if ($requests < 5) {
            return true;
        }

        return $failureRate < 0.5;
    }

    public function healthScore(string $provider): float
    {
        $metric = $this->getMetrics($provider);
        $requests = max(1, $metric['requests']);

        return round((($metric['successes'] / $requests) * 100), 2);
    }

    public function getMetrics(string $provider): array
    {
        return $this->metrics[$provider] ?? [
            'successes' => 0,
            'failures' => 0,
            'requests' => 0,
            'latency_ms' => 0,
            'updated_at' => null,
            'capabilities' => [],
        ];
    }

    private function save(): void
    {
        update_option(self::OPTION_KEY, $this->metrics);
    }
}
