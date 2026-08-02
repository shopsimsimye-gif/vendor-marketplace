<?php
namespace VMP\Modules\VendorRegistration\Services;

interface StoreSetupServiceInterface
{
    public function startSession(int $userId, int $vendorRequestId = 0): object;
    public function getSessionByUuid(string $uuid): ?object;
    public function saveStep(int $sessionId, int $step, array $payloadPart): bool;
    public function finishSession(int $sessionId, ?string $idempotencyKey = null): bool;
}
