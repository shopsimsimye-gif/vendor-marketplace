<?php
namespace VMP\Modules\VendorRegistration\Repositories;

interface StoreSetupSessionRepositoryInterface
{
    public function start(int $userId, int $vendorRequestId, array $initialPayload = []): object;
    public function findById(int $id): ?object;
    public function findByUuid(string $uuid): ?object;
    public function findActiveByUser(int $userId): ?object;
    public function findByRequest(int $vendorRequestId): ?object;
    public function saveStep(int $sessionId, int $step, array $payloadPart): bool;
    public function completeStep(int $sessionId, int $step): bool;
    public function finish(int $sessionId): bool;
    public function expire(int $sessionId): bool;
    public function delete(int $sessionId): bool;
    public function cleanupExpired(int $olderThanSeconds = 0): int;
}
