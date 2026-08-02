<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

class AIJob
{
    public function __construct(
        public string $id,
        public int $vendorId,
        public int $attachmentId = 0,
        public string $workflow = 'product-image-v1',
        public string $provider = '',
        public string $capability = 'product_generation',
        public string $status = 'QUEUED',
        public int $progress = 0,
        public string $currentStep = 'QUEUED',
        public array $result = [],
        public float $cost = 0.0,
        public array $tokens = [],
        public int $latency = 0,
        public int $retries = 0,
        public array $logs = [],
        public ?int $productId = null,
        public ?int $vendorProductId = null,
        public string $createdAt = '',
        public string $updatedAt = ''
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (int) ($data['vendor_id'] ?? 0),
            (int) ($data['attachment_id'] ?? 0),
            (string) ($data['workflow'] ?? 'product-image-v1'),
            (string) ($data['provider'] ?? ''),
            (string) ($data['capability'] ?? 'product_generation'),
            (string) ($data['status'] ?? 'QUEUED'),
            (int) ($data['progress'] ?? 0),
            (string) ($data['current_step'] ?? 'QUEUED'),
            (array) ($data['result'] ?? []),
            (float) ($data['cost'] ?? 0.0),
            (array) ($data['tokens'] ?? []),
            (int) ($data['latency'] ?? 0),
            (int) ($data['retries'] ?? 0),
            (array) ($data['logs'] ?? []),
            isset($data['product_id']) ? (int) $data['product_id'] : null,
            isset($data['vendor_product_id']) ? (int) $data['vendor_product_id'] : null,
            (string) ($data['created_at'] ?? ''),
            (string) ($data['updated_at'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendorId,
            'attachment_id' => $this->attachmentId,
            'workflow' => $this->workflow,
            'provider' => $this->provider,
            'capability' => $this->capability,
            'status' => $this->status,
            'progress' => $this->progress,
            'current_step' => $this->currentStep,
            'result' => $this->result,
            'cost' => $this->cost,
            'tokens' => $this->tokens,
            'latency' => $this->latency,
            'retries' => $this->retries,
            'logs' => $this->logs,
            'product_id' => $this->productId,
            'vendor_product_id' => $this->vendorProductId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
