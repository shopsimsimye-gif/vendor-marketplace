<?php
/**
 * Vendor Registration REST API Controller
 * Handles REST API endpoints for vendor registration
 */

namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\VendorRegistrationService;
use VMP\Core\Logger;
use VMP\Repositories\VendorRequestRepository;

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

    // ─────────────────────────────────────────────────────────────
    // Single-step vendor registration (guest + apply) — migrated from
    // legacy Modules/VendorRegistration/Controllers/RegistrationController
    // Same nonces & behavior so the front-end template keeps working.
    // ─────────────────────────────────────────────────────────────

    private const MAX_LICENSE_SIZE = 5242880; // 5MB

    private const ALLOWED_LICENSE_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Validate uploaded license file from $_FILES entry.
     */
    private function validateLicenseFile(array $file): ?\WP_Error
    {
        if (empty($file) || empty($file['name'])) {
            return null;
        }

        if ($file['size'] > self::MAX_LICENSE_SIZE) {
            return new \WP_Error('file_too_large', __('حجم ملف الرخصة يتجاوز الحد المسموح به (5 ميجابايت)', 'vmp'));
        }

        $detected = null;
        if (function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
            if ($finfo) {
                finfo_close($finfo);
            }
        }
        $mime = $detected ?: ($file['type'] ?? '');

        if (!in_array($mime, self::ALLOWED_LICENSE_MIMES, true)) {
            return new \WP_Error('invalid_mime', __('نوع ملف غير مسموح به. الأنواع المسموحة: PDF, JPG, PNG, WEBP', 'vmp'));
        }

        return null;
    }

    /**
     * Generate a unique store slug (handles UNIQUE KEY constraint).
     */
    private function uniqueSlug(string $base, VendorRequestRepository $repo): string
    {
        $slug = sanitize_title($base);
        if (empty($slug)) {
            $slug = 'vendor-store-' . time();
        }
        $candidate = $slug;
        $i         = 1;
        while ($repo->slugExists($candidate)) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    /**
     * Handle media upload and return attachment_id (0 on failure).
     */
    private function handleFileUpload(string $field): int
    {
        if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) {
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $uploaded = media_handle_upload($field, 0);
        return is_wp_error($uploaded) ? 0 : (int) $uploaded;
    }


    /**
     * تحديد خطة الاشتراك الافتراضية (المجانية) للمتجر الجديد.
     * الأولوية: الخطة النشطة ذات السعر 0 → أول خطة نشطة → 0.
     */
    private function getDefaultFreePlanId(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $planRepository = new \VMP\Repositories\SubscriptionPlanRepository();
        $plans          = $planRepository->getAll(true); // نشطة فقط

        // 1) خطة مجانية (سعر 0)
        foreach ($plans as $plan) {
            if ((float) $plan->price === 0.0) {
                return $cached = (int) $plan->id;
            }
        }

        // 2) أول خطة نشطة
        if (!empty($plans)) {
            $first = reset($plans);
            return $cached = (int) $first->id;
        }

        // 3) لا توجد خطط — يقع على 'free' العام عند الموافقة
        return $cached = 0;
    }

    /**
     * Handle guest registration (creates WP user + vendor request in one step).
     * Nonce: vmp_register_guest.
     */
    public function registerGuest(\WP_REST_Request $request): \WP_REST_Response
    {
        // ── 1. Nonce check ──
        if (!isset($_POST['vmp_register_guest_nonce']) || !wp_verify_nonce($_POST['vmp_register_guest_nonce'], 'vmp_register_guest')) {
            return new \WP_REST_Response(['error' => __('رمز الحماية غير صالح', 'vmp')], 400);
        }

        // ── 2. Collect & sanitize form data ──
        $first    = sanitize_text_field($_POST['first_name'] ?? '');
        $last     = sanitize_text_field($_POST['last_name']  ?? '');
        $username = sanitize_user($_POST['username']         ?? '');
        $phone    = sanitize_text_field($_POST['phone']      ?? '');
        $country  = sanitize_text_field($_POST['country']    ?? '');
        $email    = sanitize_email($_POST['email']           ?? '');
        $password = $_POST['password']                       ?? '';
        $accept   = !empty($_POST['accept_terms']) && $_POST['accept_terms'] == '1';

        // ── 3. Validate required fields ──
        if (!$accept) {
            return new \WP_REST_Response(['error' => __('يجب الموافقة على الشروط والأحكام', 'vmp')], 400);
        }
        if (empty($first) || empty($last) || empty($username) || empty($email) || empty($password) || empty($phone) || empty($country)) {
            return new \WP_REST_Response(['error' => __('يرجى ملء جميع الحقول المطلوبة', 'vmp')], 400);
        }
        if (!is_email($email)) {
            return new \WP_REST_Response(['error' => __('البريد الإلكتروني غير صالح', 'vmp')], 400);
        }
        if (username_exists($username)) {
            return new \WP_REST_Response(['error' => __('اسم المستخدم مسجّل مسبقاً، يرجى اختيار اسم آخر', 'vmp')], 409);
        }
        if (email_exists($email)) {
            return new \WP_REST_Response(['error' => __('البريد الإلكتروني مسجّل مسبقاً في الموقع', 'vmp')], 409);
        }

        // ── 4. Validate license file (if provided) ──
        if (!empty($_FILES['license_document']['name'])) {
            $err = $this->validateLicenseFile($_FILES['license_document']);
            if ($err instanceof \WP_Error) {
                return new \WP_REST_Response(['error' => $err->get_error_message()], 400);
            }
        }

        // ── 5. Create WP User ──
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            return new \WP_REST_Response(['error' => $user_id->get_error_message()], 500);
        }

        wp_update_user([
            'ID'           => $user_id,
            'first_name'   => $first,
            'last_name'    => $last,
            'display_name' => trim("$first $last"),
        ]);
        update_user_meta($user_id, 'phone',           $phone);
        update_user_meta($user_id, 'billing_phone',   $phone);
        update_user_meta($user_id, 'country',         $country);
        update_user_meta($user_id, 'billing_country', $country);

        // Auto log-in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        // ── 6. Handle optional file upload ──
        $attachment_id = $this->handleFileUpload('license_document');

        // ── 7. Build & insert vendor request via VendorRequestRepository ──
        $repo       = new VendorRequestRepository();
        $raw_store  = sanitize_text_field($_POST['store_name'] ?? '');
        $store_name = !empty($raw_store) ? $raw_store : sprintf(__('متجر %s', 'vmp'), $first);
        $base_slug  = !empty($_POST['store_slug']) ? sanitize_title($_POST['store_slug']) : ($username . '-store');
        $store_slug = $this->uniqueSlug($base_slug, $repo);

        $inserted_id = $repo->create([
            'user_id'           => $user_id,
            'store_name'        => $store_name,
            'store_slug'        => $store_slug,
            'store_description' => '',
            'store_address'     => $country,
            'store_phone'       => $phone,
            'store_email'       => $email,
            'whatsapp_number'   => '',
            'store_logo'        => 0,
            'store_banner'      => 0,
            'license_file'      => $attachment_id,
            'plan_id'           => $this->getDefaultFreePlanId(),
            'status'            => 'pending',
            'admin_notes'       => '',
            'terms_accepted'    => 1,
        ]);

        if (!$inserted_id) {
            $this->logger->error('registerGuest: DB insert failed for user_id=' . $user_id, ['vmp']);
        }

        // ── 8. Emails ──
        wp_mail(
            $email,
            __('تم استلام طلب انضمامك كبائع', 'vmp'),
            sprintf(__("مرحباً %s،\n\nتم استلام طلب انضمامك كبائع بنجاح. طلبك الآن قيد المراجعة من قبل المشرف.\nسنقوم بإعلامك عبر البريد الإلكتروني فور اتخاذ القرار.\n\nشكراً لانضمامك إلينا!", 'vmp'), $first)
        );
        wp_mail(
            get_option('admin_email'),
            sprintf(__('طلب انضمام بائع جديد: %s', 'vmp'), $username),
            sprintf(__("الاسم: %s %s\nاسم المستخدم: %s\nالبريد: %s\nالهاتف: %s\nالدولة: %s", 'vmp'), $first, $last, $username, $email, $phone, $country)
        );

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة من قبل المشرف. سنقوم بإرسال رسالة تفيد بالموافقة على بريدك الإلكتروني قريباً.', 'vmp'),
        ]);
    }

    /**
     * Handle apply for existing logged-in user (upgrade to vendor).
     * Nonce: vmp_register_apply.
     */
    public function apply(\WP_REST_Request $request): \WP_REST_Response
    {
        // ── 1. Auth + Nonce ──
        if (!is_user_logged_in()) {
            return new \WP_REST_Response(['error' => __('يجب تسجيل الدخول لتقديم طلب الترقية', 'vmp')], 401);
        }
        if (!isset($_POST['vmp_register_apply_nonce']) || !wp_verify_nonce($_POST['vmp_register_apply_nonce'], 'vmp_register_apply')) {
            return new \WP_REST_Response(['error' => __('رمز الحماية غير صالح', 'vmp')], 400);
        }

        $user_id = get_current_user_id();
        $user    = get_userdata($user_id);

        // ── 2. Collect & sanitize ──
        $first   = sanitize_text_field($_POST['first_name'] ?? '');
        $last    = sanitize_text_field($_POST['last_name']  ?? '');
        $phone   = sanitize_text_field($_POST['phone']      ?? '');
        $country = sanitize_text_field($_POST['country']    ?? '');
        $accept  = !empty($_POST['accept_terms']) && $_POST['accept_terms'] == '1';

        if (!$accept) {
            return new \WP_REST_Response(['error' => __('يجب الموافقة على الشروط والأحكام', 'vmp')], 400);
        }
        if (empty($phone) || empty($country)) {
            return new \WP_REST_Response(['error' => __('يرجى تزويدنا برقم الموبايل والدولة', 'vmp')], 400);
        }

        // ── 3. Validate license file ──
        if (!empty($_FILES['license_document']['name'])) {
            $err = $this->validateLicenseFile($_FILES['license_document']);
            if ($err instanceof \WP_Error) {
                return new \WP_REST_Response(['error' => $err->get_error_message()], 400);
            }
        }

        // ── 4. Update WP user profile ──
        if (!empty($first) || !empty($last)) {
            wp_update_user([
                'ID'         => $user_id,
                'first_name' => $first ?: $user->first_name,
                'last_name'  => $last  ?: $user->last_name,
            ]);
        }
        update_user_meta($user_id, 'phone',           $phone);
        update_user_meta($user_id, 'billing_phone',   $phone);
        update_user_meta($user_id, 'country',         $country);
        update_user_meta($user_id, 'billing_country', $country);

        // ── 5. File upload ──
        $attachment_id = $this->handleFileUpload('license_document');

        // ── 6. Insert or update vendor request ──
        $repo     = new VendorRequestRepository();
        $existing = $repo->findByUserId($user_id);

        $store_name = sprintf(__('متجر %s', 'vmp'), $first ?: $user->display_name);
        $store_slug = $this->uniqueSlug($user->user_login . '-store', $repo);

        if ($existing) {
            // Re-submit existing request
            $repo->update($existing->id, [
                'store_name'     => $store_name,
                'store_address'  => $country,
                'store_phone'    => $phone,
                'store_email'    => $user->user_email,
                'license_file'   => $attachment_id ?: $existing->license_file,
                'status'         => 'pending',
                'terms_accepted' => 1,
                'admin_notes'    => '',
            ]);
        } else {
            $repo->create([
                'user_id'           => $user_id,
                'store_name'        => $store_name,
                'store_slug'        => $store_slug,
                'store_description' => '',
                'store_address'     => $country,
                'store_phone'       => $phone,
                'store_email'       => $user->user_email,
                'whatsapp_number'   => '',
                'store_logo'        => 0,
                'store_banner'      => 0,
                'license_file'      => $attachment_id,
                'plan_id'           => $this->getDefaultFreePlanId(),
                'status'            => 'pending',
                'admin_notes'       => '',
                'terms_accepted'    => 1,
            ]);
        }

        // ── 7. Emails ──
        wp_mail(
            $user->user_email,
            __('تم استلام طلب ترقية حسابك إلى بائع', 'vmp'),
            sprintf(__("مرحباً %s،\n\nتم استلام طلب ترقية حسابك إلى بائع بنجاح. طلبك الآن قيد المراجعة.\n\nشكراً لتواصلك معنا!", 'vmp'), $user->display_name)
        );
        wp_mail(
            get_option('admin_email'),
            sprintf(__('طلب ترقية حساب إلى بائع: %s', 'vmp'), $user->user_login),
            sprintf(__("المستخدم: %s\nالبريد: %s\nالهاتف: %s\nالدولة: %s", 'vmp'), $user->display_name, $user->user_email, $phone, $country)
        );

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('تم تقديم طلب ترقية حسابك بنجاح! وهو الآن قيد المراجعة من قبل المشرف. سنقوم بإرسال رسالة تفيد بالموافقة على بريدك الإلكتروني قريباً.', 'vmp'),
        ]);
    }
}
