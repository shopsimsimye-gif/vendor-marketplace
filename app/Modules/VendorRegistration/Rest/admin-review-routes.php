<?php
// Admin review REST routes
add_action('rest_api_init', function() {
    $ns = 'vmp/v1';

    register_rest_route($ns, '/admin/request/(?P<id>\d+)/activate', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $repo = new \VMP\Modules\VendorRegistration\Repositories\VendorRequestRepository();
            $stateMachine = new \VMP\Modules\VendorRegistration\Services\StateMachine();
            $eventBus = new \VMP\Modules\VendorRegistration\Services\EventBus();
            $logger = new \VMP\Modules\VendorRegistration\Services\ActivityLogService();
            $service = new \VMP\Modules\VendorRegistration\Services\AdminReviewService($repo, $stateMachine, $eventBus, $logger);
            $controller = new \VMP\Modules\VendorRegistration\Controllers\AdminReviewController($service);
            return $controller->activate($request);
        },
        'permission_callback' => function() { return current_user_can('manage_vmp_requests'); },
    ]);

    register_rest_route($ns, '/admin/request/(?P<id>\d+)/request-changes', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $repo = new \VMP\Modules\VendorRegistration\Repositories\VendorRequestRepository();
            $stateMachine = new \VMP\Modules\VendorRegistration\Services\StateMachine();
            $eventBus = new \VMP\Modules\VendorRegistration\Services\EventBus();
            $logger = new \VMP\Modules\VendorRegistration\Services\ActivityLogService();
            $service = new \VMP\Modules\VendorRegistration\Services\AdminReviewService($repo, $stateMachine, $eventBus, $logger);
            $controller = new \VMP\Modules\VendorRegistration\Controllers\AdminReviewController($service);
            return $controller->requestChanges($request);
        },
        'permission_callback' => function() { return current_user_can('manage_vmp_requests'); },
    ]);

    register_rest_route($ns, '/admin/request/(?P<id>\d+)/reject', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $repo = new \VMP\Modules\VendorRegistration\Repositories\VendorRequestRepository();
            $stateMachine = new \VMP\Modules\VendorRegistration\Services\StateMachine();
            $eventBus = new \VMP\Modules\VendorRegistration\Services\EventBus();
            $logger = new \VMP\Modules\VendorRegistration\Services\ActivityLogService();
            $service = new \VMP\Modules\VendorRegistration\Services\AdminReviewService($repo, $stateMachine, $eventBus, $logger);
            $controller = new \VMP\Modules\VendorRegistration\Controllers\AdminReviewController($service);
            return $controller->reject($request);
        },
        'permission_callback' => function() { return current_user_can('manage_vmp_requests'); },
    ]);
});
