<?php
// Upload REST routes for store media
add_action('rest_api_init', function() {
    $ns = 'vmp/v1';

    register_rest_route($ns, '/store/upload/logo', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();
            $logger = new \VMP\Modules\VendorRegistration\Services\ActivityLogService();
            $controller = new \VMP\Modules\VendorRegistration\Controllers\UploadController($sessionsRepo, $logger);
            return $controller->uploadLogo($request);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    register_rest_route($ns, '/store/upload/banner', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();
            $logger = new \VMP\Modules\VendorRegistration\Services\ActivityLogService();
            $controller = new \VMP\Modules\VendorRegistration\Controllers\UploadController($sessionsRepo, $logger);
            return $controller->uploadBanner($request);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    register_rest_route($ns, '/store/upload/logo', [
        'methods' => 'DELETE',
        'callback' => function(\WP_REST_Request $request) {
            $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();
            $logger = new \VMP\Modules\VendorRegistration\Services\ActivityLogService();
            $controller = new \VMP\Modules\VendorRegistration\Controllers\UploadController($sessionsRepo, $logger);
            return $controller->deleteLogo($request);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    register_rest_route($ns, '/store/upload/banner', [
        'methods' => 'DELETE',
        'callback' => function(\WP_REST_Request $request) {
            $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();
            $logger = new \VMP\Modules\VendorRegistration\Services\ActivityLogService();
            $controller = new \VMP\Modules\VendorRegistration\Controllers\UploadController($sessionsRepo, $logger);
            return $controller->deleteBanner($request);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);
});
