<?php
namespace VMP\Modules\VendorRegistration\Repositories;

interface VendorRequestRepositoryInterface {
    public function find(int $id): ?object;
    public function findByUser(int $userId): ?object;
    public function create(array $data): object;
    public function update(int $id, array $data): bool;
    public function updateStatus(int $id, string $status, ?string $reason = null): bool;
    public function logTransition(int $requestId, string $from, string $to, int $changedBy = 0, ?string $reason = null): void;
}
