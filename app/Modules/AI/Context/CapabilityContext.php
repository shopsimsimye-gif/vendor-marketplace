<?php
namespace VMP\Modules\AI\Context;

defined('ABSPATH') || exit;

/**
 * Context object used for provider capabilities and failover-aware requests.
 */
class CapabilityContext
{
    public function __construct(
        private string $capability,
        private array $payload = [],
        private string $workflow = 'product-image-v1',
        private ?int $vendorId = null,
        private ?string $language = null,
        private ?string $model = null,
        private bool $stream = false,
        private array $metadata = []
    ) {
    }

    public static function vision(string $image, array $options = [], array $metadata = []): self
    {
        return new self('vision', ['image' => $image, 'options' => $options], $options['workflow'] ?? 'product-image-v1', $options['vendor_id'] ?? null, $options['language'] ?? null, $options['model'] ?? null, false, $metadata);
    }

    public static function search(string $query, array $options = [], array $metadata = []): self
    {
        return new self('search', ['query' => $query, 'options' => $options], $options['workflow'] ?? 'product-image-v1', $options['vendor_id'] ?? null, $options['language'] ?? null, $options['model'] ?? null, false, $metadata);
    }

    public static function text(array $messages, array $options = [], array $metadata = []): self
    {
        return new self('text', ['messages' => $messages, 'options' => $options], $options['workflow'] ?? 'product-image-v1', $options['vendor_id'] ?? null, $options['language'] ?? null, $options['model'] ?? null, false, $metadata);
    }

    public static function imageGeneration(string $prompt, array $options = [], array $metadata = []): self
    {
        return new self('image_generation', ['prompt' => $prompt, 'options' => $options], $options['workflow'] ?? 'product-image-v1', $options['vendor_id'] ?? null, $options['language'] ?? null, $options['model'] ?? null, false, $metadata);
    }

    public static function streaming(array $messages, array $options = [], array $metadata = []): self
    {
        return new self('streaming', ['messages' => $messages, 'options' => $options], $options['workflow'] ?? 'product-image-v1', $options['vendor_id'] ?? null, $options['language'] ?? null, $options['model'] ?? null, true, $metadata);
    }

    public function capability(): string
    {
        return $this->capability;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function workflow(): string
    {
        return $this->workflow;
    }

    public function vendorId(): ?int
    {
        return $this->vendorId;
    }

    public function language(): ?string
    {
        return $this->language;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function isStreaming(): bool
    {
        return $this->stream;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function withVendorId(int $vendorId): self
    {
        $clone = clone $this;
        $clone->vendorId = $vendorId;

        return $clone;
    }

    public function withModel(string $model): self
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    public function toArray(): array
    {
        return [
            'capability' => $this->capability,
            'payload' => $this->payload,
            'workflow' => $this->workflow,
            'vendor_id' => $this->vendorId,
            'language' => $this->language,
            'model' => $this->model,
            'stream' => $this->stream,
            'metadata' => $this->metadata,
        ];
    }
}
