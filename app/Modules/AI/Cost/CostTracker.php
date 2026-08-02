<?php
namespace VMP\Modules\AI\Cost;

defined('ABSPATH') || exit;

/**
 * Class CostTracker
 *
 * Description of administrative platform component CostTracker.
 *
 * @package vendor-marketplace
 */
class CostTracker
{
    /** @var AIUsage[] */
    private array $usage = [];

    /**
     * Reset functionality helper.
     *
     * @return void Output payload.
     */
    public function reset(): void
    {
        $this->usage = [];
    }

    /**
     * Record functionality helper.
     *
     * @param array|AIUsage $usage Description index.
     * @return AIUsage Output payload.
     */
    public function record(array|AIUsage $usage): AIUsage
    {
        $entry = $usage instanceof AIUsage ? $usage : AIUsage::fromArray($usage);
        $this->usage[] = $entry;

        return $entry;
    }

    /**
     * FromProviderResponse functionality helper.
     *
     * @param string $capability Description index.
     * @param array $response Description index.
     * @return AIUsage Output payload.
     */
    public function fromProviderResponse(string $capability, array $response): AIUsage
    {
        $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];

        return $this->record([
            'provider' => (string) ($response['provider'] ?? ''),
            'capability' => $capability,
            'input_tokens' => (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0),
            'images' => (int) ($usage['images'] ?? 0),
            'searches' => (int) ($usage['searches'] ?? 0),
            'cost' => (float) ($usage['cost'] ?? 0.0),
            'latency_ms' => (int) ($response['latency_ms'] ?? 0),
            'metadata' => $usage,
        ]);
    }

    /**
     * Summary functionality helper.
     *
     * @return array Output payload.
     */
    public function summary(): array
    {
        $summary = [
            'requests' => count($this->usage),
            'tokens' => 0,
            'images' => 0,
            'searches' => 0,
            'cost' => 0.0,
            'latency_ms' => 0,
            'providers' => [],
        ];

        foreach ($this->usage as $usage) {
            $summary['tokens'] += $usage->tokens();
            $summary['images'] += $usage->images;
            $summary['searches'] += $usage->searches;
            $summary['cost'] += $usage->cost;
            $summary['latency_ms'] += $usage->latencyMs;
            $summary['providers'][$usage->provider] = ($summary['providers'][$usage->provider] ?? 0) + 1;
        }

        return $summary;
    }
}
