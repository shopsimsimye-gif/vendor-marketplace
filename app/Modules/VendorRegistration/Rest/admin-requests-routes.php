<?php
// Admin requests REST routes
use VMP\Modules\VendorRegistration\Services\PermissionService;

add_action('rest_api_init', function() {
    $ns = 'vmp/v1';

    register_rest_route($ns, '/admin/requests', [
        'methods' => 'GET',
        'callback' => function(\WP_REST_Request $request) {
            $controller = new \VMP\Modules\VendorRegistration\Controllers\AdminRequestsController();
            return $controller->listRequests($request);
        },
        'permission_callback' => function() { return \VMP\Modules\VendorRegistration\Services\PermissionService::canManageVendorRequests(); },
    ]);

    register_rest_route($ns, '/admin/requests/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => function(\WP_REST_Request $request) {
            $controller = new \VMP\Modules\VendorRegistration\Controllers\AdminRequestsController();
            return $controller->getRequest($request);
        },
        'permission_callback' => function() { return \VMP\Modules\VendorRegistration\Services\PermissionService::canManageVendorRequests(); },
    ]);

    register_rest_route($ns, '/admin/requests/(?P<id>\d+)/health', [
        'methods' => 'GET',
        'callback' => function(\WP_REST_Request $request) {
            $controller = new \VMP\Modules\VendorRegistration\Controllers\AdminRequestsController();
            return $controller->healthSummary($request);
        },
        'permission_callback' => function() { return \VMP\Modules\VendorRegistration\Services\PermissionService::canManageVendorRequests(); },
    ]);

    register_rest_route($ns, '/admin/requests/(?P<id>\d+)/activity', [
        'methods' => 'GET',
        'callback' => function(\WP_REST_Request $request) {
            $controller = new \VMP\Modules\VendorRegistration\Controllers\AdminRequestsController();
            return $controller->getActivity($request);
        },
        'permission_callback' => function() { return \VMP\Modules\VendorRegistration\Services\PermissionService::canManageVendorRequests(); },
    ]);

    register_rest_route($ns, '/admin/requests/bulk', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $controller = new \VMP\Modules\VendorRegistration\Controllers\AdminRequestsController();
            return $controller->bulkAction($request);
        },
        'permission_callback' => function() { return \VMP\Modules\VendorRegistration\Services\PermissionService::canManageVendorRequests(); },
    ]);

});
