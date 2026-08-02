<?php
namespace VMP\Core\Queue;

defined('ABSPATH') || exit;

/**
 * Class Job
 *
 * نموذج يمثل كينونة الوظيفة داخل قاعدة البيانات
 */
class Job
{
    public function __construct(
        public readonly int $id,
        public readonly string $jobClass,
        public readonly array $payload,
        public readonly string $status = 'pending',
        public readonly int $attempts = 0,
        public readonly ?string $errorMessage = null,
        public readonly ?string $lockedAt = null,
        public readonly ?string $createdAt = null
    ) {}

    /**
     * بناء نموذج من كائن صف قاعدة البيانات
     */
    public static function fromDbRow(object $row): self
    {
        $payload = [];
        if (!empty($row->payload)) {
            $decoded = json_decode($row->payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return new self(
            id: (int) $row->id,
            jobClass: $row->job_class,
            payload: $payload,
            status: $row->status,
            attempts: (int) $row->attempts,
            errorMessage: $row->error_message,
            lockedAt: $row->locked_at,
            createdAt: $row->created_at
        );
    }
}
