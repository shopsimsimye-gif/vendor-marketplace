<?php
namespace VMP\Modules\AI\Cost;

defined('ABSPATH') || exit;

use JsonSerializable;

/**
 * Class AIUsage
 *
 * Description of administrative platform component AIUsage.
 *
 * @package vendor-marketplace
 */
class AIUsage implements JsonSerializable
{
    public function __construct(
        public readonly string $provider = '',
        public readonly string $capability = '',
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $images = 0,
        public readonly int $searches = 0,
        public readonly float $cost = 0.0,
        public readonly int $latencyMs = 0,
        public readonly array $metadata = []
    ) {
    }

    /**
     * FromArray functionality helper.
     *
     * @param array $data Description index.
     * @return self Output payload.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) ($data['provider'] ?? ''),
            capability: (string) ($data['capability'] ?? ''),
            inputTokens: (int) ($data['input_tokens'] ?? 0),
            outputTokens: (int) ($data['output_tokens'] ?? 0),
            images: (int) ($data['images'] ?? 0),
            searches: (int) ($data['searches'] ?? 0),
            cost: (float) ($data['cost'] ?? 0.0),
            latencyMs: (int) ($data['latency_ms'] ?? 0),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : []
        );
    }

    /**
     * Tokens functionality helper.
     *
     * @return int Output payload.
     */
    public function tokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'capability' => $this->capability,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'tokens' => $this->tokens(),
            'images' => $this->images,
            'searches' => $this->searches,
            'cost' => $this->cost,
            'latency_ms' => $this->latencyMs,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * JsonSerialize functionality helper.
     *
     * @return array Output payload.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
