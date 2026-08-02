<?php
namespace VMP\Modules\VendorRegistration\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use VMP\Repositories\VendorRequestRepository;

/**
 * Registration Controller
 * Handles single-step vendor registration (guest) and upgrade (logged-in).
 * Uses VendorRequestRepository (the same one used by VendorRequestsAdminPage).
 */
class RegistrationController {

    // file upload constraints
    private const MAX_LICENSE_SIZE   = 5242880; // 5MB
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
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
            if ($finfo) finfo_close($finfo);
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
        $i = 1;
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
     * Handle guest registration (creates WP user + vendor request in one step).
     */
    public function registerGuest(WP_REST_Request $request): WP_REST_Response
    {
        // ── 1. Nonce check ──
        if (!isset($_POST['vmp_register_guest_nonce']) || !wp_verify_nonce($_POST['vmp_register_guest_nonce'], 'vmp_register_guest')) {
            return new WP_REST_Response(['error' => __('رمز الحماية غير صالح', 'vmp')], 400);
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
            return new WP_REST_Response(['error' => __('يجب الموافقة على الشروط والأحكام', 'vmp')], 400);
        }
        if (empty($first) || empty($last) || empty($username) || empty($email) || empty($password) || empty($phone) || empty($country)) {
            return new WP_REST_Response(['error' => __('يرجى ملء جميع الحقول المطلوبة', 'vmp')], 400);
        }
        if (!is_email($email)) {
            return new WP_REST_Response(['error' => __('البريد الإلكتروني غير صالح', 'vmp')], 400);
        }
        if (username_exists($username)) {
            return new WP_REST_Response(['error' => __('اسم المستخدم مسجّل مسبقاً، يرجى اختيار اسم آخر', 'vmp')], 409);
        }
        if (email_exists($email)) {
            return new WP_REST_Response(['error' => __('البريد الإلكتروني مسجّل مسبقاً في الموقع', 'vmp')], 409);
        }

        // ── 4. Validate license file (if provided) ──
        if (!empty($_FILES['license_document']['name'])) {
            $err = $this->validateLicenseFile($_FILES['license_document']);
            if ($err instanceof \WP_Error) {
                return new WP_REST_Response(['error' => $err->get_error_message()], 400);
            }
        }

        // ── 5. Create WP User ──
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            return new WP_REST_Response(['error' => $user_id->get_error_message()], 500);
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
            'plan_id'           => 0,
            'status'            => 'pending',
            'admin_notes'       => '',
            'terms_accepted'    => 1,
        ]);

        if (!$inserted_id) {
            error_log('[VMP] registerGuest: DB insert failed for user_id=' . $user_id . ' | last_error=' . $GLOBALS['wpdb']->last_error);
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

        return new WP_REST_Response([
            'success' => true,
            'message' => __('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة من قبل المشرف. سنقوم بإرسال رسالة تفيد بالموافقة على بريدك الإلكتروني قريباً.', 'vmp'),
        ]);
    }

    /**
     * Handle apply for existing logged-in user (upgrade to vendor).
     */
    public function apply(WP_REST_Request $request): WP_REST_Response
    {
        // ── 1. Auth + Nonce ──
        if (!is_user_logged_in()) {
            return new WP_REST_Response(['error' => __('يجب تسجيل الدخول لتقديم طلب الترقية', 'vmp')], 401);
        }
        if (!isset($_POST['vmp_register_apply_nonce']) || !wp_verify_nonce($_POST['vmp_register_apply_nonce'], 'vmp_register_apply')) {
            return new WP_REST_Response(['error' => __('رمز الحماية غير صالح', 'vmp')], 400);
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
            return new WP_REST_Response(['error' => __('يجب الموافقة على الشروط والأحكام', 'vmp')], 400);
        }
        if (empty($phone) || empty($country)) {
            return new WP_REST_Response(['error' => __('يرجى تزويدنا برقم الموبايل والدولة', 'vmp')], 400);
        }

        // ── 3. Validate license file ──
        if (!empty($_FILES['license_document']['name'])) {
            $err = $this->validateLicenseFile($_FILES['license_document']);
            if ($err instanceof \WP_Error) {
                return new WP_REST_Response(['error' => $err->get_error_message()], 400);
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
        $repo       = new VendorRequestRepository();
        $existing   = $repo->findByUserId($user_id);

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
                'plan_id'           => 0,
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

        return new WP_REST_Response([
            'success' => true,
            'message' => __('تم تقديم طلب ترقية حسابك بنجاح! وهو الآن قيد المراجعة من قبل المشرف. سنقوم بإرسال رسالة تفيد بالموافقة على بريدك الإلكتروني قريباً.', 'vmp'),
        ]);
    }

    /**
     * Legacy endpoint — kept for backward compatibility.
     */
    public function register(WP_REST_Request $request): WP_REST_Response
    {
        return $this->registerGuest($request);
    }

    /**
     * Save draft (not critical for the main flow).
     */
    public function saveDraft(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['error' => 'Unauthorized'], 401);
        }
        // Draft not critical — just acknowledge
        return new WP_REST_Response(['success' => true]);
    }
}
