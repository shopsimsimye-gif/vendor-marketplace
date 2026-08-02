<?php
// Register store-setup REST routes (extended)
add_action('rest_api_init', function() {
    $ns = 'vmp/v1';

    // Using closures so we can inject dependencies (no DI container available)
    register_rest_route($ns, '/store-setup/start', [
        'methods' => 'POST',
        'callback' => function(
            \WP_REST_Request $request
        ) {
            $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();
            $requestsRepo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository();
            $storesRepo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorStoreRepository();
            $bus = new \VMP\Modules\VendorRegistration\Services\EventBus();
            $idemp = new \VMP\Modules\VendorRegistration\Services\IdempotencyService();
            $service = new \VMP\Modules\VendorRegistration\Services\StoreSetupService($sessionsRepo, $requestsRepo, $storesRepo, $bus, $idemp);
            $controller = new \VMP\Modules\VendorRegistration\Controllers\StoreSetupController($sessionsRepo, $service, $idemp);
            return $controller->start($request);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    register_rest_route($ns, '/store-setup/state', [
        'methods' => 'GET',
        'callback' => function(\WP_REST_Request $request) {
            $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();
            $requestsRepo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository();
            $storesRepo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorStoreRepository();
            $bus = new \VMP\Modules\VendorRegistration\Services\EventBus();
            $idemp = new \VMP\Modules\VendorRegistration\Services\IdempotencyService();
            $service = new \VMP\Modules\VendorRegistration\Services\StoreSetupService($sessionsRepo, $requestsRepo, $storesRepo, $bus, $idemp);
            $controller = new \VMP\Modules\VendorRegistration\Controllers\StoreSetupController($sessionsRepo, $service, $idemp);
            return $controller->state($request);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    register_rest_route($ns, '/store-setup/step/(?P<step>\d+)', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();
            $requestsRepo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository();
            $storesRepo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorStoreRepository();
            $bus = new \VMP\Modules\VendorRegistration\Services\EventBus();
            $idemp = new \VMP\Modules\VendorRegistration\Services\IdempotencyService();
            $service = new \VMP\Modules\VendorRegistration\Services\StoreSetupService($sessionsRepo, $requestsRepo, $storesRepo, $bus, $idemp);
            $controller = new \VMP\Modules\VendorRegistration\Controllers\StoreSetupController($sessionsRepo, $service, $idemp);
            return $controller->step($request);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
        'args' => [ 'step' => ['validate_callback' => function($v){ return in_array((int)$v, [1,2,3,4,5], true); }] ]
    ]);

    register_rest_route($ns, '/store-setup/finish', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();
            $requestsRepo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository();
            $storesRepo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorStoreRepository();
            $bus = new \VMP\Modules\VendorRegistration\Services\EventBus();
            $idemp = new \VMP\Modules\VendorRegistration\Services\IdempotencyService();
            $service = new \VMP\Modules\VendorRegistration\Services\StoreSetupService($sessionsRepo, $requestsRepo, $storesRepo, $bus, $idemp);
            $controller = new \VMP\Modules\VendorRegistration\Controllers\StoreSetupController($sessionsRepo, $service, $idemp);
            return $controller->finish($request);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);
});
