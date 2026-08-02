<?php
namespace VMP\Modules\VendorRegistration\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use VMP\Modules\VendorRegistration\DTOs\StoreSetupDTO;
use VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository;
use VMP\Modules\VendorRegistration\Repositories\WpVendorStoreRepository;
use VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository;
use VMP\Modules\VendorRegistration\Services\StoreSetupService;
use VMP\Modules\VendorRegistration\Services\EventBus;
use VMP\Modules\VendorRegistration\Services\IdempotencyService;

class StoreController
{
    private StoreSetupService $service;

    public function __construct()
    {
        $sessionsRepo = new StoreSetupRepository();
        $requestsRepo = new WpVendorRequestRepository();
        $storesRepo   = new WpVendorStoreRepository();
        $bus          = new EventBus();
        $idemp        = new IdempotencyService();

        $this->service = new StoreSetupService($sessionsRepo, $requestsRepo, $storesRepo, $bus, $idemp);
    }

    public function setup(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        if (!$user_id) return new WP_REST_Response(['error' => 'Unauthorized'], 401);

        $body = $request->get_json_params();
        $dto = new StoreSetupDTO($body);
        try {
            $session = $this->service->startSession($user_id, $dto->vendor_request_id ?? 0);
            return new WP_REST_Response(['success' => true, 'session' => $session]);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function getStore(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        if (!$user_id) return new WP_REST_Response(['error' => 'Unauthorized'], 401);
        $repo = new WpVendorStoreRepository();
        $store = $repo->findByVendor($user_id);
        if (!$store) return new WP_REST_Response(['store' => null]);
        return new WP_REST_Response(['store' => $store]);
    }
}
