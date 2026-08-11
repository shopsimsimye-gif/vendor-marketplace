<?php
/**
 * AjaxController — معالج طلبات AJAX العامة
 *
 * يدعم: التحقق من Slug/Email، رفع الميديا، التحقق من حالة الطلب،
 * والتسجيل/الترقية بنظام الخطوة الواحدة.
 *
 * @package VMP\Http\Controllers\Ajax
 * @since 3.0.0
 */

namespace VMP\Http\Controllers\Ajax;

defined('ABSPATH') || exit;

use VMP\Services\VendorRegistrationService;
use VMP\Core\Container;

class AjaxController
{
    private VendorRegistrationService $registrationService;

    public function __construct()
    {
        $this->registrationService = Container::getInstance()->make(VendorRegistrationService::class);
    }

    /**
     * تسجيل مسارات AJAX
     */
    public function registerAjaxActions(): void
    {
        // ── التحقق من توفر رابط المتجر ──
        $this->addAction('wp_ajax_vmp_check_store_slug', [$this, 'checkStoreSlug']);
        $this->addAction('wp_ajax_nopriv_vmp_check_store_slug', [$this, 'checkStoreSlug']);

        // ── التحقق من توفر البريد الإلكتروني ──
        $this->addAction('wp_ajax_vmp_check_email', [$this, 'checkEmail']);
        $this->addAction('wp_ajax_nopriv_vmp_check_email', [$this, 'checkEmail']);

        // ── رفع ملفات الميديا (يتطلب تسجيل دخول) ──
        $this->addAction('wp_ajax_vmp_upload_media', [$this, 'handleMediaUpload']);

        // ── التحقق من حالة الطلب ──
        $this->addAction('wp_ajax_vmp_check_request_status', [$this, 'checkRequestStatus']);
        $this->addAction('wp_ajax_nopriv_vmp_check_request_status', [$this, 'checkRequestStatus']);

        // ── التسجيل/الترقية بنظام الخطوة الواحدة ──
        $this->addAction('wp_ajax_vmp_vendor_register_single', [$this, 'handleSingleStepRegister']);
        $this->addAction('wp_ajax_nopriv_vmp_vendor_register_single', [$this, 'handleSingleStepRegister']);
    }

    /* ═══════════════════════════════════════════════════════════════════
       1. التحقق من توفر رابط المتجر (Slug)
       ═══════════════════════════════════════════════════════════════════ */

    public function checkStoreSlug(): void
    {
        $this->verifyNonceOrDie('vmp_public_nonce', 'vmp_vendor_check_slug');

        $slug = $this->sanitizeSlug((string) ($this->requestValue('slug') ?? ''));
        $excludeUserId = absint($this->requestValue('exclude_user_id', 0));

        if (empty($slug)) {
            $this->sendJsonError(['message' => __('slug فارغ', 'vmp')]);
            return;
        }

        if (strlen($slug) < 3) {
            $this->sendJsonSuccess([
                'available' => false,
                'slug'      => $slug,
                'message'   => __('الحد الأدنى 3 أحرف', 'vmp'),
            ]);
            return;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $this->sendJsonSuccess([
                'available' => false,
                'slug'      => $slug,
                'message'   => __('أحرف إنجليزية صغيرة، أرقام، وشرطات فقط', 'vmp'),
            ]);
            return;
        }

        // Rate limiting: 10 محاولات / دقيقة / IP
        if ($this->isRateLimited('check_slug', 10, MINUTE_IN_SECONDS)) {
            $this->sendJsonError(['message' => __('عدد المحاولات مرتفع، يرجى الانتظار.', 'vmp')], 429);
            return;
        }

        $exists = $this->registrationService->slugExists($slug, $excludeUserId);

        $this->sendJsonSuccess([
            'available' => !$exists,
            'slug'      => $slug,
            'message'   => $exists
                ? __('الرابط مستخدم مسبقاً', 'vmp')
                : __('الرابط متاح', 'vmp'),
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════════
       2. التحقق من توفر البريد الإلكتروني
       ═══════════════════════════════════════════════════════════════════ */

    public function checkEmail(): void
    {
        $this->verifyNonceOrDie('vmp_public_nonce', 'vmp_vendor_check_email');

        $email = sanitize_email((string) ($this->requestValue('email') ?? ''));
        $excludeUserId = absint($this->requestValue('exclude_user_id', 0));

        if (empty($email)) {
            $this->sendJsonError(['message' => __('بريد إلكتروني فارغ', 'vmp')]);
            return;
        }

        if (!is_email($email)) {
            $this->sendJsonSuccess([
                'available' => false,
                'email'     => $email,
                'message'   => __('بريد إلكتروني غير صحيح', 'vmp'),
            ]);
            return;
        }

        // Rate limiting: 10 محاولات / دقيقة / IP
        if ($this->isRateLimited('check_email', 10, MINUTE_IN_SECONDS)) {
            $this->sendJsonError(['message' => __('عدد المحاولات مرتفع، يرجى الانتظار.', 'vmp')], 429);
            return;
        }

        $exists = $this->registrationService->emailExists($email, $excludeUserId);

        $this->sendJsonSuccess([
            'available' => !$exists,
            'email'     => $email,
            'message'   => $exists
                ? __('هذا البريد الإلكتروني مسجّل مسبقاً', 'vmp')
                : __('البريد الإلكتروني متاح', 'vmp'),
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════════
       3. رفع ملفات الميديا (شعار، غلاف، رخصة)
       ═══════════════════════════════════════════════════════════════════ */

    public function handleMediaUpload(): void
    {
        // التحقق من تسجيل الدخول وقدرة الرفع
        if (!is_user_logged_in()) {
            $this->sendJsonError(['message' => __('يجب تسجيل الدخول لرفع الملفات.', 'vmp')], 403);
            return;
        }

        if (!current_user_can('upload_files')) {
            $this->sendJsonError(['message' => __('ليس لديك صلاحية رفع الملفات.', 'vmp')], 403);
            return;
        }

        $this->verifyNonceOrDie('vmp_public_nonce', 'vmp_vendor_media_upload');

        if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
            $this->sendJsonError(['message' => __('لم يتم رفع ملف.', 'vmp')]);
            return;
        }

        // التحقق من نوع الملف والحجم
        $fileType = sanitize_text_field(wp_unslash($_POST['type'] ?? 'logo'));
        $allowedTypes = ['logo', 'banner', 'license'];
        if (!in_array($fileType, $allowedTypes, true)) {
            $this->sendJsonError(['message' => __('نوع الملف غير مسموح.', 'vmp')]);
            return;
        }

        $result = $this->registrationService->handleMediaUpload($_FILES['file'], $fileType);

        if (is_wp_error($result)) {
            $this->sendJsonError(['message' => $result->get_error_message()]);
            return;
        }

        $this->sendJsonSuccess($result);
    }

    /* ═══════════════════════════════════════════════════════════════════
       4. التحقق من حالة طلب المستخدم الحالي
       ═══════════════════════════════════════════════════════════════════ */

    public function checkRequestStatus(): void
    {
        $this->verifyNonceOrDie('vmp_public_nonce', 'vmp_vendor_registration_nonce');

        if (!is_user_logged_in()) {
            $this->sendJsonError(['code' => 'not_logged_in']);
            return;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            $this->sendJsonError(['code' => 'user_not_found']);
            return;
        }

        $vendor = $this->registrationService->getVendorByUser($user->ID);
        $request = $this->registrationService->getPendingRequestByUser($user->ID);

        // البائع معتمد بالفعل
        if ($vendor) {
            $this->sendJsonSuccess([
                'status'    => 'approved',
                'vendor_id' => (int) $vendor->id,
                'message'   => $this->registrationService->getSetting(
                    'approval_message',
                    __('تهانينا! تم قبول طلبك وأصبحت بائعاً.', 'vmp')
                ),
            ]);
            return;
        }

        // طلب قيد المراجعة أو مرفوض
        if ($request) {
            $message = ($request->status === 'rejected')
                ? $this->registrationService->getSetting(
                    'rejection_message',
                    __('تم رفض طلبك.', 'vmp') . ' ' . ($request->admin_notes ?? '')
                )
                : $this->registrationService->getSetting(
                    'pending_approval_message',
                    __('طلبك قيد المراجعة، يرجى الانتظار.', 'vmp')
                );

            $this->sendJsonSuccess([
                'status'      => sanitize_text_field($request->status),
                'request_id'    => (int) $request->id,
                'message'       => $message,
                'admin_notes'   => sanitize_text_field($request->admin_notes ?? ''),
            ]);
            return;
        }

        // لا يوجد طلب
        $this->sendJsonSuccess(['status' => 'none']);
    }

    /* ═══════════════════════════════════════════════════════════════════
       5. التسجيل / الترقية بنظام الخطوة الواحدة (Single-Step)
       ═══════════════════════════════════════════════════════════════════ */

    public function handleSingleStepRegister(): void
    {
        $isLoggedIn = is_user_logged_in();
        $nonceAction = $isLoggedIn ? 'vmp_register_apply' : 'vmp_register_guest';

        $this->verifyNonceOrDie(
            $isLoggedIn ? 'vmp_register_apply_nonce' : 'vmp_register_guest_nonce',
            $nonceAction
        );

        // Rate limiting: 3 محاولات / 5 دقائق / IP
        if ($this->isRateLimited('single_register', 3, 5 * MINUTE_IN_SECONDS)) {
            $this->sendJsonError(
                ['message' => __('عدد المحاولات مرتفع، يرجى الانتظار قليلاً.', 'vmp')],
                429
            );
            return;
        }

        // ── تنظيف المدخلات الأساسية ──
        $data = [
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name'  => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            'phone'      => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'country'    => sanitize_text_field(wp_unslash($_POST['country'] ?? '')),
        ];

        // ── التحقق من الموافقة على الشروط ──
        $acceptTerms = !empty($_POST['accept_terms']);
        if (!$acceptTerms) {
            $this->sendJsonError(['message' => __('يجب الموافقة على الشروط والأحكام.', 'vmp')]);
            return;
        }

        if ($isLoggedIn) {
            // ═══════════════════════════════════════════════════════════
            // ترقية مستخدم مسجل (Apply)
            // ═══════════════════════════════════════════════════════════
            $user = wp_get_current_user();

            if ($this->registrationService->getVendorByUser($user->ID)) {
                $this->sendJsonError(['message' => __('أنت مسجل كبائع بالفعل.', 'vmp')]);
                return;
            }

            $pending = $this->registrationService->getPendingRequestByUser($user->ID);
            if ($pending && in_array($pending->status, ['submitted', 'pending'], true)) {
                $this->sendJsonError(['message' => __('لديك طلب قيد المراجعة بالفعل.', 'vmp')]);
                return;
            }

            // تحديث بيانات المستخدم (اختياري)
            if (!empty($data['first_name'])) {
                wp_update_user(['ID' => $user->ID, 'first_name' => $data['first_name']]);
            }
            if (!empty($data['last_name'])) {
                wp_update_user(['ID' => $user->ID, 'last_name' => $data['last_name']]);
            }

            // رفع ترخيص النشاط التجاري (اختياري)
            $licenseId = $this->maybeUploadLicense();

            $requestData = array_merge($data, [
                'user_id'           => $user->ID,
                'email'             => $user->user_email,
                'username'          => $user->user_login,
                'license_document'  => $licenseId,
                'status'            => 'pending',
            ]);

            $requestId = $this->registrationService->createRequest($requestData);

            if (!$requestId) {
                if ($licenseId) {
                    wp_delete_attachment($licenseId, true);
                }
                $this->sendJsonError(['message' => __('فشل إنشاء الطلب، يرجى المحاولة مرة أخرى.', 'vmp')]);
                return;
            }

            do_action('vmp_vendor_request_submitted', $requestId, $user->ID, $requestData);

            $this->sendSuccessResponse($requestId, $isLoggedIn);
            return;
        }

        // ═══════════════════════════════════════════════════════════════
        // تسجيل زائر جديد (Guest Register)
        // ═══════════════════════════════════════════════════════════════
        $data['username'] = sanitize_user(wp_unslash($_POST['username'] ?? ''));
        $data['email']     = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $data['password']  = $_POST['password'] ?? ''; // سيتم تجزئتها لاحقاً، لا تنظف

        // التحقق من الحقول المطلوبة
        $required = ['first_name', 'last_name', 'username', 'email', 'phone', 'country', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                /* translators: %s: field name */
                $this->sendJsonError(['message' => sprintf(__('الحقل %s مطلوب.', 'vmp'), $field)]);
                return;
            }
        }

        if (strlen($data['password']) < 8) {
            $this->sendJsonError(['message' => __('كلمة المرور يجب أن تكون 8 أحرف على الأقل.', 'vmp')]);
            return;
        }

        if (!is_email($data['email'])) {
            $this->sendJsonError(['message' => __('بريد إلكتروني غير صحيح.', 'vmp')]);
            return;
        }

        if ($this->registrationService->emailExists($data['email'])
            || $this->registrationService->slugExists(sanitize_title($data['username']))) {
            // مكافحة التعداد: رسالة عامة لا تميّز بين البريد واسم المستخدم
            $this->sendJsonError(['message' => __('بعض البيانات مستخدمة بالفعل، يرجى التحقق والمحاولة مرة أخرى.', 'vmp')]);
            return;
        }

        // إنشاء مستخدم WordPress
        $userId = wp_insert_user([
            'user_login'   => $data['username'],
            'user_email'   => $data['email'],
            'user_pass'    => $data['password'],
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'display_name' => $data['first_name'] . ' ' . $data['last_name'],
            'role'         => 'customer',
        ]);

        if (is_wp_error($userId)) {
            $this->sendJsonError(['message' => $userId->get_error_message()]);
            return;
        }

        // حفظ بيانات إضافية
        update_user_meta($userId, 'billing_phone', $data['phone']);
        update_user_meta($userId, 'billing_country', $data['country']);

        // رفع ترخيص النشاط التجاري (اختياري)
        $licenseId = $this->maybeUploadLicense();

        $requestData = array_merge($data, [
            'user_id'           => $userId,
            'license_document'  => $licenseId,
            'status'            => 'pending',
        ]);

        $requestId = $this->registrationService->createRequest($requestData);

        if (!$requestId) {
            // تنظيف: حذف المرفق + المستخدم إذا فشل إنشاء الطلب
            if ($licenseId) {
                wp_delete_attachment($licenseId, true);
            }
            wp_delete_user($userId);
            $this->sendJsonError(['message' => __('فشل إنشاء الطلب بعد إنشاء الحساب. يرجى المحاولة مرة أخرى.', 'vmp')]);
            return;
        }

        // تسجيل الدخول تلقائياً (اختياري)
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true);

        do_action('vmp_vendor_request_submitted', $requestId, $userId, $requestData);

        $this->sendSuccessResponse($requestId, $isLoggedIn);
    }

    /* ═══════════════════════════════════════════════════════════════════
       Helpers
       ═══════════════════════════════════════════════════════════════════ */

    /**
     * رفع ترخيص النشاط التجاري إذا وُجد
     */
    private function maybeUploadLicense(): int
    {
        if (!isset($_FILES['license_document']) || empty($_FILES['license_document']['tmp_name'])) {
            return 0;
        }

        $result = $this->registrationService->handleMediaUpload($_FILES['license_document'], 'license');

        return (!is_wp_error($result) && !empty($result['id']))
            ? (int) $result['id']
            : 0;
    }

    /**
     * إرسال استجابة النجاح الموحدة
     */
    private function sendSuccessResponse(int $requestId, bool $isLoggedIn): void
    {
        $settings = get_option('vmp_settings', []);
        $redirect = $settings['registration']['redirect_after_submit'] ?? home_url('/');

        $this->sendJsonSuccess([
            'message'     => $this->registrationService->getSetting(
                'register_success_message',
                __('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة.', 'vmp')
            ),
            'request_id'  => $requestId,
            'redirect_to' => $redirect,
            'is_new_user' => !$isLoggedIn,
        ]);
    }

    /**
     * التحقق من nonce أو إنهاء التنفيذ
     */
    private function verifyNonceOrDie(string $nonceField, string $nonceAction): void
    {
        $nonce = sanitize_text_field(wp_unslash($_POST[$nonceField] ?? $_REQUEST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, $nonceAction)) {
            $this->sendJsonError(
                ['message' => __('رمز التحقق غير صالح. يرجى تحديث الصفحة والمحاولة مرة أخرى.', 'vmp')],
                403
            );
        }
    }

    /**
     * Rate limiting: يفوض إلى Security::isRateLimited (جدول DB ذري).
     */
    private function isRateLimited(string $action, int $maxAttempts, int $window): bool
    {
        // userId=0 → يتم تحديد الهوية عبر IP (Security::getClientIp).
        return \VMP\Support\Security::isRateLimited('ajax_' . $action, 0, $maxAttempts, $window);
    }

    /**
     * IP العميل الحقيقي (Cloudflare-aware فقط).
     */
    private function getClientIp(): string
    {
        return \VMP\Support\Security::getClientIp();
    }

    /**
     * جلب قيمة من $_POST مع wp_unslash
     */
    private function requestValue(string $key, $default = null)
    {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
    }

    /**
     * تنظيف slug
     */
    private function sanitizeSlug(string $value): string
    {
        return sanitize_title($value);
    }

    /**
     * تسجيل action في WordPress
     */
    private function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * إرسال JSON نجاح
     */
    private function sendJsonSuccess(array $data): void
    {
        wp_send_json_success($data);
    }

    /**
     * إرسال JSON خطأ مع HTTP status code
     */
    private function sendJsonError(array $data, int $statusCode = 400): void
    {
        wp_send_json_error($data, $statusCode);
    }
}
