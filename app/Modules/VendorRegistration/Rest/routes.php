<?php
// Rest routes for vendor registration (extended with store setup endpoints)
add_action('rest_api_init', function() {
    $regController = new \VMP\Modules\VendorRegistration\Controllers\RegistrationController();

    register_rest_route('vmp/v1', '/vendor/register-guest', [
        'methods'  => 'POST',
        'callback' => [$regController, 'registerGuest'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('vmp/v1', '/vendor/apply', [
        'methods'  => 'POST',
        'callback' => [$regController, 'apply'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('vmp/v1', '/vendor/register', [
        'methods'  => 'POST',
        'callback' => [$regController, 'register'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('vmp/v1', '/vendor/draft', [
        'methods'  => 'POST',
        'callback' => [$regController, 'saveDraft'],
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    register_rest_route('vmp/v1', '/vendor/draft', [
        'methods'  => 'GET',
        'callback' => function() {
            $user_id = get_current_user_id();
            if (!$user_id) return new \WP_REST_Response(['error' => 'Unauthorized'], 401);
            $repo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository();
            $existing = $repo->findByUser($user_id);
            if (!$existing) return new \WP_REST_Response(['draft' => null]);
            return new \WP_REST_Response(['draft' => json_decode($existing->draft_data, true)]);
        },
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    // Store setup endpoints
    $storeController = new \VMP\Modules\VendorRegistration\Controllers\StoreController();
    register_rest_route('vmp/v1', '/vendor/store/setup', [
        'methods'  => 'POST',
        'callback' => [$storeController, 'setup'],
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    register_rest_route('vmp/v1', '/vendor/store', [
        'methods'  => 'GET',
        'callback' => [$storeController, 'getStore'],
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    // Admin approve/reject endpoints
    $adminController = new \VMP\Modules\VendorRegistration\Controllers\AdminController();
    register_rest_route('vmp/v1', '/admin/vendor/(?P<id>\d+)/approve', [
        'methods'  => 'POST',
        'callback' => [$adminController, 'approve'],
        'permission_callback' => function() { return current_user_can('manage_vmp_requests'); },
    ]);

    register_rest_route('vmp/v1', '/admin/vendor/(?P<id>\d+)/reject', [
        'methods'  => 'POST',
        'callback' => [$adminController, 'reject'],
        'permission_callback' => function() { return current_user_can('manage_vmp_requests'); },
    ]);
});
