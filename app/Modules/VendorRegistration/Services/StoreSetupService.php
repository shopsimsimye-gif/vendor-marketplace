<?php
namespace VMP\Modules\VendorRegistration\Services;

use VMP\Modules\VendorRegistration\Repositories\StoreSetupSessionRepositoryInterface;
use VMP\Modules\VendorRegistration\Repositories\VendorRequestRepositoryInterface;
use VMP\Modules\VendorRegistration\Repositories\VendorStoreRepositoryInterface;

class StoreSetupService implements StoreSetupServiceInterface
{
    public function __construct(
        private StoreSetupSessionRepositoryInterface $sessionsRepo,
        private VendorRequestRepositoryInterface $requestsRepo,
        private VendorStoreRepositoryInterface $storesRepo,
        private EventBus $bus,
        private IdempotencyService $idemp
    ) {
    }

    public function startSession(int $userId, int $vendorRequestId = 0): object
    {
        return $this->sessionsRepo->start($userId, $vendorRequestId, []);
    }

    public function getSessionByUuid(string $uuid): ?object
    {
        return $this->sessionsRepo->findByUuid($uuid);
    }

    public function saveStep(int $sessionId, int $step, array $payloadPart): bool
    {
        // Server side validation should be done by callers (validators)
        return $this->sessionsRepo->saveStep($sessionId, $step, $payloadPart);
    }

    public function finishSession(int $sessionId, ?string $idempotencyKey = null): bool
    {
        // Idempotency guard
        if ($idempotencyKey && $this->idemp->exists($idempotencyKey)) {
            return true;
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $session = $this->sessionsRepo->findById($sessionId);
            if (!$session) {
                throw new \RuntimeException('Session not found');
            }

            // ensure all steps completed
            $completed = json_decode($session->completed_steps, true) ?: [];
            for ($i = 1; $i <= 5; $i++) {
                if (!in_array($i, $completed, true)) {
                    throw new \RuntimeException('Incomplete steps');
                }
            }

            // mark session completed
            $ok = $this->sessionsRepo->finish($sessionId);
            if (!$ok) throw new \RuntimeException('Failed to mark session completed');

            // update vendor request status via StateMachine flow
            $request = $this->requestsRepo->find((int)$session->vendor_request_id);
            if ($request) {
                // transition request status to store_setup_completed via repository
                $this->requestsRepo->updateStatus((int)$request->id, 'store_setup_completed', null);
            }

            // create store record (deferred details: storesRepo create method expects array)
            // Build store data from payload
            $payload = json_decode($session->payload, true) ?: [];
            $storeData = [
                'vendor_id' => (int)$session->user_id,
                'store_name' => $payload['store']['store_name'] ?? null,
                'store_slug' => $payload['store']['store_slug'] ?? null,
                'description' => $payload['store']['description'] ?? null,
                'logo' => $payload['branding']['logo'] ?? null,
                'banner' => $payload['branding']['banner'] ?? null,
                'contact' => wp_json_encode($payload['contact'] ?? []),
                'policies' => wp_json_encode($payload['policies'] ?? []),
                'social' => wp_json_encode($payload['social'] ?? []),
                'setup_completed' => 1,
                'is_active' => 0,
            ];

            // Use store repository to create; it should use SlugGeneratorService internally if slug empty/collision
            $created = $this->storesRepo->create($storeData);
            if (!$created) throw new \RuntimeException('Failed to create store');

            // commit
            $wpdb->query('COMMIT');

            // Mark idempotency after commit
            if ($idempotencyKey) $this->idemp->mark($idempotencyKey);

            // Dispatch event after commit
            $event = new \VMP\Modules\VendorRegistration\Events\StoreSetupCompleted($this->sessionsRepo->findById($sessionId));
            $this->bus->dispatch($event);

            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log('StoreSetupService::finishSession failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
