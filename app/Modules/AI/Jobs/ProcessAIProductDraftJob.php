<?php
namespace VMP\Modules\AI\Jobs;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Core\Queue\JobInterface;
use VMP\Modules\AI\Services\AIProductDraftService;

class ProcessAIProductDraftJob implements JobInterface
{
    public function __construct(private array $payload)
    {
    }

    public function handle(): void
    {
        $jobId = sanitize_text_field($this->payload['job_id'] ?? '');
        $vendorId = (int) ($this->payload['vendor_id'] ?? 0);

        if ($jobId === '' || $vendorId <= 0) {
            throw new \RuntimeException('Invalid AI product job payload.');
        }

        Container::getInstance()
            ->make(AIProductDraftService::class)
            ->processQueuedJob($jobId, $vendorId);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }
}
