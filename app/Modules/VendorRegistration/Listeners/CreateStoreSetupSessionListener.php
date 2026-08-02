<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Events\VendorApproved;
use VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository;
use VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository;

class CreateStoreSetupSessionListener
{
    public function __invoke(VendorApproved $event): void
    {
        $userId = $event->request->user_id ?? 0;
        $requestId = $event->request->id ?? 0;
        if (!$userId || !$requestId) return;

        // create a store setup session row
        $repo = new StoreSetupRepository();
        $existing = $repo->findByRequest($requestId);
        if ($existing) {
            // reset to initial state
            $repo->update($existing->id, ['current_step' => 1, 'payload' => '{}', 'completed_steps' => '[]']);
            return;
        }

        $repo->create([
            'user_id' => $userId,
            'vendor_request_id' => $requestId,
            'current_step' => 1,
            'payload' => '{}',
            'completed_steps' => '[]',
        ]);
    }
}
