/**
 * Vendor Registration REST API Controller
 * Handles REST API endpoints for vendor registration
 */

namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\VendorRegistrationService;
use VMP\Core\Logger;

/**
 * Class RestVendorRegistrationController
 *
 * REST API endpoints for vendor registration flow
 */
class RestVendorRegistrationController
{
    private VendorRegistrationService $service;
    private Logger $logger;

    public function __construct(VendorRegistrationService $service, Logger $logger)
    {
        $this->service = $service;
        $this->logger = $logger;
    }

    /**
     * Register REST routes
     */
    public function registerRoutes(): void
    {
        // Step 1: Verify user / basic data
        register_rest_route('vmp/v1', '/vendor-register/step1', [
            'methods'             => 'POST',
            'callback'            => [$this, 'step1VerifyUser'],
            'permission_callback' => '__return_true',
        ]);

        // Step 2: Store data
        register_rest_route('vmp/v1', '/vendor-register/step2', [
            'methods'             => 'POST',
            'callback'            => [$this, 'step2StoreData'],
            'permission_callback' => '__return_true',
        ]);

        // Check slug availability
        register_rest_route('vmp/v1', '/vendor-register/check-slug', [
            'methods'             => 'POST',
            'callback'            => [$this, 'checkSlugAvailability'],
            'permission_callback' => '__return_true',
        ]);

        // Upload media
        register_rest_route('vmp/v1', '/vendor-register/upload-media', [
            'methods'             => 'POST',
            'callback'            => [$this, 'uploadMedia'],
            'permission_callback' => '__return_true',
        ]);

        // Submit final request
        register_rest_route('vmp/v1', '/vendor-register/submit', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submitRequest'],
            'permission_callback' => '__return_true',
        ]);

        // Check request status
        register_rest_route('vmp/v1', '/vendor-register/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'checkRequestStatus'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    /**
     * Step 1: Verify user / basic data
     */
    public function step1VerifyUser(\WP_REST_Request $request): \WP_REST_Response
    {
        // Verify nonce
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'vmp_vendor_registration_nonce')) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'invalid_nonce',
                'message' => __('رمز الأمان غير صحيح', 'vmp'),
            ], 403);
        }

        if (!is_user_logged_in()) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'not_logged_in',
                'message' => __('يجب تسجيل الدخول أولاً', 'vmp'),
            ], 401);
        }

        $user = wp_get_current_user();

        // Check pending request
        $existingRequest = $this->service->getPendingRequestByUser($user->ID);
        if ($existingRequest) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'pending_request_exists',
                'message' => __('لديك طلب قيد المراجعة بالفعل', 'vmp'),
                'data'    => ['request_id' => $existingRequest->id],
            ], 409);
        }

        // Check existing vendor
        $existingVendor = $this->service->getVendorByUser($user->ID);
        if ($existingVendor) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'vendor_exists',
                'message' => __('لديك متجر مسجل مسبقاً', 'vmp'),
            ], 409);
        }

        // For logged-in users, update full name if provided
        $fullName = sanitize_text_field($request->get_param('full_name') ?? '');
        $profileUpdated = false;
        
        if ($fullName) {
            $profileUpdated = $this->service->updateUserFullName($user->ID, $fullName);
        }

        return new \WP_REST_Response([
            'success'         => true,
            'message'         => __('تم التحقق بنجاح', 'vmp'),
            'profile_updated' => $profileUpdated,
            'skip_step1'      => true,
            'user'            => [
                'id'            => $user->ID,
                'email'         => $user->user_email,
                'display_name'  => $user->display_name,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
            ],
        ], 200);
    }

    /**
     * Step 2: Store data validation and temporary save
     */
    public function step2StoreData(\WP_REST_Request $request): \WP_REST_Response
    {
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'vmp_vendor_registration_nonce')) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'invalid_nonce',
                'message' => __('رمز الأمان غير صحيح', 'vmp'),
            ], 403);
        }

        if (!is_user_logged_in()) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'not_logged_in',
                'message' => __('يجب تسجيل الدخول أولاً', 'vmp'),
            ], 401);
        }

        $user = wp_get_current_user();

        // Sanitize input
        $data = [
            'store_name'        => sanitize_text_field($request->get_param('store_name') ?? ''),
            'store_slug'        => sanitize_title($request->get_param('store_slug') ?? ''),
            'store_description' => sanitize_textarea_field($request->get_param('store_description') ?? ''),
            'store_address'     => sanitize_textarea_field($request->get_param('store_address') ?? ''),
            'store_phone'       => sanitize_text_field($request->get_param('store_phone') ?? ''),
            'store_email'       => sanitize_email($request->get_param('store_email') ?? ''),
            'whatsapp_number'   => sanitize_text_field($request->get_param('whatsapp_number') ?? ''),
            'store_logo'        => absint($request->get_param('store_logo') ?? 0),
            'store_banner'      => absint($request->get_param('store_banner') ?? 0),
            'license_file'      => absint($request->get_param('license_file') ?? 0),
        ];

        // Apply filters
        $data = apply_filters('vmp_vendor_registration_step2_data', $data, $user->ID);

        // Validate
        $errors = $this->service->validateStep2Data($data);
        if (!empty($errors)) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'validation_failed',
                'message' => __('يرجى تصحيح الأخطاء', 'vmp'),
                'errors'  => $errors,
            ], 422);
        }

        // Check slug availability
        $slug = $data['store_slug'];
        if ($this->service->slugExists($slug, $user->ID)) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'slug_exists',
                'field'   => 'store_slug',
                'message' => __('رابط المتجر مستخدم مسبقاً', 'vmp'),
            ], 409);
        }

        // Save to transient
        $transientKey = "vmp_vendor_reg_step2_{$user->ID}";
        set_transient($transientKey, $data, HOUR_IN_SECONDS);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('تم حفظ بيانات المتجر', 'vmp'),
            'slug'    => $slug,
        ], 200);
    }

    /**
     * Check slug availability (debounced)
     */
    public function checkSlugAvailability(\WP_REST_Request $request): \WP_REST_Response
    {
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'vmp_vendor_registration_nonce')) {
            return new \WP_REST_Response([
                'success'   => false,
                'available' => false,
                'message'   => __('رمز الأمان غير صحيح', 'vmp'),
            ], 403);
        }

        $slug = sanitize_title($request->get_param('slug') ?? '');
        
        if (empty($slug)) {
            return new \WP_REST_Response([
                'success'   => true,
                'available' => false,
                'message'   => '',
            ], 200);
        }

        $userId = is_user_logged_in() ? get_current_user_id() : 0;
        $exists = $this->service->slugExists($slug, $userId);

        return new \WP_REST_Response([
            'success'   => true,
            'available' => !$exists,
            'message'   => $exists ? __('رابط المتجر مستخدم مسبقاً', 'vmp') : __('رابط المتجر متاح', 'vmp'),
        ], 200);
    }

    /**
     * Upload media file
     */
    public function uploadMedia(\WP_REST_Request $request): \WP_REST_Response
    {
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'vmp_vendor_registration_nonce')) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'invalid_nonce',
                'message' => __('رمز الأمان غير صحيح', 'vmp'),
            ], 403);
        }

        if (!is_user_logged_in()) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'not_logged_in',
                'message' => __('يجب تسجيل الدخول', 'vmp'),
            ], 401);
        }

        // Handle file upload via $_FILES since REST API doesn't handle multipart well
        if (!isset($_FILES['file'])) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'no_file',
                'message' => __('لم يتم اختيار ملف', 'vmp'),
            ], 400);
        }

        $type = sanitize_text_field($request->get_param('type') ?? 'logo');
        $allowedTypes = ['logo', 'banner', 'license'];
        
        if (!in_array($type, $allowedTypes, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'invalid_type',
                'message' => __('نوع ملف غير صالح', 'vmp'),
            ], 400);
        }

        $result = $this->service->handleMediaUpload($_FILES['file'], $type);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'upload_failed',
                'message' => $result->get_error_message(),
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('تم رفع الملف بنجاح', 'vmp'),
            'file'    => $result,
        ], 200);
    }

    /**
     * Submit final request
     */
    public function submitRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'vmp_vendor_registration_nonce')) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'invalid_nonce',
                'message' => __('رمز الأمان غير صحيح', 'vmp'),
            ], 403);
        }

        if (!is_user_logged_in()) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'not_logged_in',
                'message' => __('يجب تسجيل الدخول أولاً', 'vmp'),
            ], 401);
        }

        $user = wp_get_current_user();

        // Retrieve step 2 data from transient
        $transientKey = "vmp_vendor_reg_step2_{$user->ID}";
        $step2Data = get_transient($transientKey);
        
        if (!$step2Data) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'missing_step2_data',
                'message' => __('انتهت صلاحية الجلسة، يرجى البدء من جديد', 'vmp'),
            ], 400);
        }

        // Sanitize submit data
        $submitData = [
            'terms_accepted' => (bool) $request->get_param('terms_accepted'),
            'plan_id'        => absint($request->get_param('plan_id') ?? 0),
        ];

        // Validate
        $errors = $this->service->validateSubmitData($submitData);
        if (!empty($errors)) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'validation_failed',
                'message' => __('يرجى تصحيح الأخطاء', 'vmp'),
                'errors'  => $errors,
            ], 422);
        }

        // Merge all data
        $data = array_merge($step2Data, $submitData);
        $data['user_id'] = $user->ID;

        // Apply filters
        $data = apply_filters('vmp_vendor_request_data', $data, $user->ID);

        // Action before creation
        do_action('vmp_before_vendor_request_create', $data, $user->ID);

        // Create request
        $requestId = $this->service->createRequest($data);

        if (!$requestId) {
            return new \WP_REST_Response([
                'success' => false,
                'code'    => 'creation_failed',
                'message' => __('فشل في إنشاء الطلب، يرجى المحاولة مرة أخرى', 'vmp'),
            ], 500);
        }

        // Clean up transient
        delete_transient($transientKey);

        // Action after creation
        do_action('vmp_after_vendor_request_create', $requestId, $data, $user->ID);

        // Fire event for notifications
        do_action('vmp_vendor_request_submitted', $requestId, $user->ID, $data);

        // Get success message
        $successMessage = apply_filters('vmp_vendor_register_success_message',
            $this->service->getSetting('register_success_message',
                __('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة من قبل الإدارة.', 'vmp')
            ),
            $requestId
        );

        return new \WP_REST_Response([
            'success'     => true,
            'message'     => $successMessage,
            'request_id'  => $requestId,
            'redirect_to' => $this->service->getSetting('redirect_after_submit', home_url('/my-account/')),
        ], 201);
    }

    /**
     * Check request status for current user
     */
    public function checkRequestStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!is_user_logged_in()) {
            return new \WP_REST_Response([
                'success' => true,
                'status'  => 'none',
            ], 200);
        }

        $user = wp_get_current_user();
        $pendingRequest = $this->service->getPendingRequestByUser($user->ID);
        $vendor = $this->service->getVendorByUser($user->ID);

        if ($vendor) {
            return new \WP_REST_Response([
                'success'  => true,
                'status'   => 'approved',
                'vendor_id'=> $vendor->id,
                'message'  => $this->service->getSetting('approval_message',
                    __('تهانينا! تم قبول طلبك وأصبحت بائعاً.', 'vmp')
                ),
            ], 200);
        }

        if ($pendingRequest) {
            $message = ($pendingRequest->status === 'rejected')
                ? $this->service->getSetting('rejection_message',
                    __('تم رفض طلبك. السبب: ', 'vmp') . $pendingRequest->admin_notes
                )
                : $this->service->getSetting('pending_approval_message',
                    __('طلبك قيد المراجعة، يرجى الانتظار.', 'vmp')
                );

            return new \WP_REST_Response([
                'success'      => true,
                'status'       => $pendingRequest->status,
                'request_id'   => $pendingRequest->id,
                'message'      => $message,
                'admin_notes'  => $pendingRequest->admin_notes ?? '',
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => true,
            'status'  => 'none',
        ], 200);
    }
}