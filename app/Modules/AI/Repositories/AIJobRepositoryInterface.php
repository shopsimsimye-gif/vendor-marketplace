<?php
namespace VMP\Modules\AI\Repositories;

defined('ABSPATH') || exit;

interface AIJobRepositoryInterface
{
    public function create(array $data): array;
    public function find(string $id): ?array;
    public function findForVendor(string $id, int $vendorId): ?array;
    public function update(string $id, array $data): ?array;
    public function appendLog(string $id, string $level, string $message, array $context = []): void;

    // Timeline / events
    public function appendEvent(string $jobId, string $eventType, array $payload = []): void;
    public function getTimeline(string $jobId, int $limit = 200): array;
}
