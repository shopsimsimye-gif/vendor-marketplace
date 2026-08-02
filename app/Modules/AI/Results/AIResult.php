<?php
namespace VMP\Modules\AI\Results;

defined('ABSPATH') || exit;

use JsonSerializable;

/**
 * Class AIResult
 *
 * Description of administrative platform component AIResult.
 *
 * @package vendor-marketplace
 */
class AIResult implements JsonSerializable
{
    public function __construct(
        public readonly string $title = '',
        public readonly string $description = '',
        public readonly string $shortDescription = '',
        public readonly array $keywords = [],
        public readonly array $specifications = [],
        public readonly float $confidence = 0.0,
        public readonly array $warnings = [],
        public readonly string $provider = '',
        public readonly int $latencyMs = 0,
        public readonly int $tokens = 0,
        public readonly float $cost = 0.0,
        public readonly array $sources = [],
        public readonly string $status = 'draft',
        public readonly string $reviewStatus = 'pending_review',
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
            title: (string) ($data['title'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            shortDescription: (string) ($data['short_description'] ?? ''),
            keywords: is_array($data['keywords'] ?? null) ? $data['keywords'] : [],
            specifications: is_array($data['specifications'] ?? null) ? $data['specifications'] : [],
            confidence: (float) ($data['confidence'] ?? 0.0),
            warnings: is_array($data['warnings'] ?? null) ? $data['warnings'] : [],
            provider: (string) ($data['provider'] ?? ''),
            latencyMs: (int) ($data['latency_ms'] ?? 0),
            tokens: (int) ($data['tokens'] ?? 0),
            cost: (float) ($data['cost'] ?? 0.0),
            sources: is_array($data['sources'] ?? null) ? $data['sources'] : [],
            status: (string) ($data['status'] ?? 'draft'),
            reviewStatus: (string) ($data['review_status'] ?? 'pending_review'),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : []
        );
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'short_description' => $this->shortDescription,
            'keywords' => $this->keywords,
            'specifications' => $this->specifications,
            'confidence' => $this->confidence,
            'warnings' => $this->warnings,
            'provider' => $this->provider,
            'latency_ms' => $this->latencyMs,
            'tokens' => $this->tokens,
            'cost' => $this->cost,
            'sources' => $this->sources,
            'status' => $this->status,
            'review_status' => $this->reviewStatus,
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
