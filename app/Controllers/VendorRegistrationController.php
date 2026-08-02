<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Http\Requests\RegisterVendorRequest;
use VMP\DTO\RegisterVendorDTO;
use VMP\Services\VendorRegistrationService;
use VMP\Core\Container;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\VendorRequestRepositoryInterface;
use VMP\Repositories\VendorRepository;
use VMP\Repositories\VendorRequestRepository;

/**
 * متحكم تسجيل البائعين - يدير الخطوات الثلاث للتسجيل
 */
class VendorRegistrationController
{
    private VendorRegistrationService $service;
    private array $settings;

    public function __construct(VendorRegistrationService $service = null)
    {
        // استخدام Container للحصول على الخدمة إذا لم تُمرر مباشرة
        $this->service = $service ?? Container::getInstance()->make(VendorRegistrationService::class);
        
        $this->settings = get_option('vmp_settings', []);
        $this->settings = is_array($this->settings['registration'] ?? null) 
            ? $this->settings['registration'] 
            : $this->settings;
    }

    /**
     * عرض صفحة التسجيل (الخطوة 1)
     */
    public function showStep1(): void
    {
        // التحقق من وجود صفحة تسجيل محددة
        $page_id = $this->settings['registration_page_id'] ?? 0;
        if ($page_id && is_page($page_id)) {
            // السماح للقالب بالتعامل مع العرض
            return;
        }

        // التحقق من حالة المستخدم
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $existing_request = $this->service->getPendingRequestByUser($user->ID);
            $existing_vendor = $this->service->getVendorByUser($user->ID);

            if ($existing_vendor) {
                // المستخدم بائع معتمد - إعادة التوجيه
                $this->redirectToDashboard();
            }
            
            if ($existing_request) {
                // طلب قيد الانتظار - عرض حالته
                $this->renderRequestStatus($existing_request);
                return;
            }
        }

        // عرض نموذج الخطوة 1
        $this->renderStep1();
    }

    /**
     * معالجة الخطوة 1 (AJAX)
     */
    public function handleStep1(): void
    {
        check_ajax_referer('vmp_vendor_register_step1', 'nonce');

        if (!is_user_logged_in()) {
            $request = RegisterVendorRequest::fromPost('vmp_vendor_register_step1', 'vmp_step1_nonce');
        } else {
            // للمستخدمين المسجلين، نستخدم بيانات الجلسة
            $request = RegisterVendorRequest::from([]);
        }

        if (!$request->validate()) {
            wp_send_json_error([
                'code'    => 'validation_error',
                'message' => $request->firstError(),
                'errors'  => $request->errors(),
            ]);
        }

        $dto = $request->toDTO();
        $cleanData = $dto->toArray();

        // إذا كان المستخدم غير مسجل، نقوم بإنشاء حساب ووردبريس جديد
        if (!is_user_logged_in()) {
            $user_id = $this->createUserFromDTO($dto);
            if (is_wp_error($user_id)) {
                wp_send_json_error([
                    'code'    => 'user_creation_failed',
                    'message' => $user_id->get_error_message(),
                ]);
            }
            $cleanData['user_id'] = $user_id;
            $this->saveToSession('user_id', $user_id);
            
            // تسجيل دخول المستخدم تلقائياً
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
        }

        // حفظ في الجلسة
        $this->saveToSession('step1', $cleanData);

        // إذا كان المستخدم مسجلاً، ننتقل للخطوة 2 مباشرة
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $this->saveToSession('user_id', $user->ID);
            $dto->user_id = $user->ID;
            
            // تحديث الاسم الكامل إذا تغير
            if (!empty($dto->full_name)) {
                $this->service->updateUserFullName($user->ID, $dto->full_name);
            }
        }

        wp_send_json_success([
            'message'     => __('تم حفظ البيانات، جاري الانتقال...', 'vmp'),
            'redirect_to' => '#step2',
            'data'        => $cleanData,
        ]);
    }

    /**
     * إنشاء مستخدم ووردبريس جديد من بيانات التسجيل
     */
    private function createUserFromDTO(\VMP\DTO\RegisterVendorDTO $dto): int|\WP_Error
    {
        // التحقق من عدم وجود البريد الإلكتروني مسبقاً
        if (email_exists($dto->user_email)) {
            return new \WP_Error('email_exists', __('هذا البريد الإلكتروني مسجّل مسبقاً.', 'vmp'));
        }

        // إنشاء المستخدم
        $user_id = wp_create_user($dto->user_email, $dto->user_pass, $dto->user_email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // تحديث بيانات المستخدم
        wp_update_user([
            'ID'           => $user_id,
            'first_name'   => $dto->first_name,
            'last_name'    => $dto->last_name,
            'display_name' => trim("{$dto->first_name} {$dto->last_name}") ?: $dto->store_name,
        ]);

        return $user_id;
    }


    /**
     * عرض الخطوة 2
     */
    public function showStep2(): void
    {
        if (!$this->validateStep(1)) {
            $this->redirectToStep(1);
            return;
        }

        $this->renderStep2();
    }

    /**
     * معالجة الخطوة 2 (AJAX)
     */
    public function handleStep2(): void
    {
        check_ajax_referer('vmp_vendor_register_step2', 'nonce');

        $sessionData = $this->getSessionData('step1');
        if (!$sessionData) {
            wp_send_json_error([
                'code'    => 'session_expired',
                'message' => __('انتهت صلاحية الجلسة، يرجى البدء من جديد.', 'vmp'),
            ]);
        }

        $request = RegisterVendorRequest::fromPost('vmp_vendor_register_step2', 'vmp_step2_nonce');
        // دمج بيانات الخطوة 1 مع الخطوة 2
        $request->data = array_merge($sessionData, $request->data);

        if (!$request->validate()) {
            wp_send_json_error([
                'code'    => 'validation_error',
                'message' => $request->firstError(),
                'errors'  => $request->errors(),
            ]);
        }

        $dto = $request->toDTO();
        $cleanData = $dto->toArray();
        
        $this->saveToSession('step2', $cleanData);

        wp_send_json_success([
            'message'     => __('تم حفظ بيانات المتجر، جاري الانتقال...', 'vmp'),
            'redirect_to' => '#step3',
            'data'        => $cleanData,
        ]);
    }

    /**
     * عرض الخطوة 3 (الشروط + خطة الاشتراك)
     */
    public function showStep3(): void
    {
        if (!$this->validateStep(2)) {
            $this->redirectToStep(1);
            return;
        }

        $this->renderStep3();
    }

    /**
     * معالجة الإرسال النهائي (الخطوة 3)
     */
    public function handleSubmit(): void
    {
        check_ajax_referer('vmp_vendor_register_step3', 'nonce');

        $step1Data = $this->getSessionData('step1');
        $step2Data = $this->getSessionData('step2');

        if (!$step1Data || !$step2Data) {
            wp_send_json_error([
                'code'    => 'session_expired',
                'message' => __('انتهت صلاحية الجلسة، يرجى البدء من جديد.', 'vmp'),
            ]);
        }

        // دمج جميع البيانات
        $allData = array_merge($step1Data, $step2Data, $_POST);
        $allData['user_id'] = $step1Data['user_id'] ?? 0;

        $request = RegisterVendorRequest::fromPost('vmp_vendor_register_step3', 'vmp_step3_nonce');
        $request->data = $allData;

        if (!$request->validate()) {
            wp_send_json_error([
                'code'    => 'validation_error',
                'message' => $request->firstError(),
                'errors'  => $request->errors(),
            ]);
        }

        $dto = $request->toDTO();
        $requestData = $dto->getRequestData();

        // إنشاء الطلب
        $requestId = $this->service->createRequest($requestData);

        if (!$requestId) {
            wp_send_json_error([
                'code'    => 'create_failed',
                'message' => __('فشل إنشاء الطلب، يرجى المحاولة مرة أخرى.', 'vmp'),
            ]);
        }

        // تنظيف الجلسة
        $this->clearSession();

        // رسالة النجاح
        $successMessage = apply_filters('vmp_vendor_register_success_message',
            $this->service->getSetting('register_success_message',
                __('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة من قبل الإدارة.', 'vmp')
            ),
            $requestId
        );

        // رابط التوجيه
        $redirectUrl = $this->service->getSetting('redirect_after_submit', home_url('/my-account/'));

        // إطلاق حدث للإشعارات
        do_action('vmp_vendor_request_submitted', $requestId, $dto->user_id, $requestData);

        wp_send_json_success([
            'message'     => $successMessage,
            'request_id'  => $requestId,
            'redirect_to' => $redirectUrl,
        ]);
    }

    /**
     * رفع ملفات الميديا (AJAX)
     */
    public function handleMediaUpload(): void
    {
        check_ajax_referer('vmp_vendor_media_upload', 'nonce');

        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => __('لم يتم رفع ملف', 'vmp')]);
        }

        $type = sanitize_text_field($_POST['type'] ?? 'logo');
        $result = $this->service->handleMediaUpload($_FILES['file'], $type);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    /**
     * التحقق من توفر الـ slug (AJAX)
     */
    public function checkSlugAvailability(): void
    {
        check_ajax_referer('vmp_vendor_check_slug', 'nonce');

        $slug = sanitize_title($_POST['slug'] ?? '');
        $excludeUserId = absint($_POST['exclude_user_id'] ?? 0);

        if (empty($slug)) {
            wp_send_json_error(['message' => __('slug فارغ', 'vmp')]);
        }

        $exists = $this->service->slugExists($slug, $excludeUserId);

        wp_send_json_success([
            'available' => !$exists,
            'slug'      => $slug,
            'message'   => $exists ? __('الرابط مستخدم مسبقاً', 'vmp') : __('الرابط متاح', 'vmp'),
        ]);
    }

    /**
     * التحقق من توفر البريد الإلكتروني (AJAX)
     */
    public function checkEmailAvailability(): void
    {
        check_ajax_referer('vmp_vendor_check_email', 'nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        $excludeUserId = absint($_POST['exclude_user_id'] ?? 0);

        if (empty($email)) {
            wp_send_json_error(['message' => __('بريد إلكتروني فارغ', 'vmp')]);
        }

        $exists = $this->service->emailExists($email, $excludeUserId);

        wp_send_json_success([
            'available' => !$exists,
            'email'     => $email,
            'message'   => $exists ? __('البريد الإلكتروني مسجل مسبقاً', 'vmp') : __('البريد الإلكتروني متاح', 'vmp'),
        ]);
    }

    /**
     * حالة طلب المستخدم الحالي (AJAX)
     */
    public function checkRequestStatus(): void
    {
        check_ajax_referer('vmp_vendor_registration_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['code' => 'not_logged_in']);
        }

        $user = wp_get_current_user();
        $request = $this->service->getPendingRequestByUser($user->ID);
        $vendor = $this->service->getVendorByUser($user->ID);

        if ($vendor) {
            wp_send_json_success([
                'status'    => 'approved',
                'vendor_id' => $vendor->id,
                'message'   => $this->service->getSetting('approval_message',
                    __('تهانينا! تم قبول طلبك وأصبحت بائعاً.', 'vmp')
                ),
            ]);
            return;
        }

        if ($request) {
            $message = ($request->status === 'rejected')
                ? $this->service->getSetting('rejection_message',
                    __('تم رفض طلبك. السبب: ', 'vmp') . $request->admin_notes
                )
                : $this->service->getSetting('pending_approval_message',
                    __('طلبك قيد المراجعة، يرجى الانتظار.', 'vmp')
                );

            wp_send_json_success([
                'status'       => $request->status,
                'request_id'   => $request->id,
                'message'      => $message,
                'admin_notes'  => $request->admin_notes ?? '',
            ]);
            return;
        }

        wp_send_json_success(['status' => 'none']);
    }

    /**
     * عرض حالة طلب موجود
     */
    private function renderRequestStatus($request): void
    {
        $message = ($request->status === 'rejected')
            ? $this->service->getSetting('rejection_message',
                __('تم رفض طلبك. السبب: {reason}', 'vmp')
            )
            : $this->service->getSetting('pending_approval_message',
                __('طلبك قيد المراجعة. سيتم إعلامك بالبريد الإلكتروني عند اتخاذ قرار.', 'vmp')
            );

        $message = str_replace('{reason}', $request->admin_notes ?? '', $message);

        $type = $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'error' : 'info');

        $this->renderTemplate('vendor-register-status', [
            'request'  => $request,
            'message'  => $message,
            'type'     => $type,
            'settings' => $this->settings,
        ]);
    }

    /**
     * عرض نموذج الخطوة 1
     */
    private function renderStep1(): void
    {
        $formData = $this->getSessionData('step1') ?? [];
        $errors = $this->getSessionData('errors') ?? [];
        
        $this->renderTemplate('vendor-register-step1', [
            'step_key'   => 'step1',
            'form_data'  => $formData,
            'errors'     => $errors,
            'current_user' => wp_get_current_user(),
            'settings'   => $this->settings,
        ]);
    }

    /**
     * عرض نموذج الخطوة 2
     */
    private function renderStep2(): void
    {
        $formData = $this->getSessionData('step2') ?? [];
        $step1Data = $this->getSessionData('step1') ?? [];
        $errors = $this->getSessionData('errors') ?? [];

        // دمج البيانات للقالب
        $combinedData = array_merge($step1Data, $formData);

        // خطة الاشتراك الافتراضية
        $defaultPlanId = $this->getDefaultPlanId();

        $this->renderTemplate('vendor-register-step2', [
            'step_key'         => 'step2',
            'form_data'        => $combinedData,
            'errors'           => $errors,
            'settings'         => $this->settings,
            'default_plan_id'  => $defaultPlanId,
        ]);
    }

    /**
     * عرض نموذج الخطوة 3
     */
    private function renderStep3(): void
    {
        $step1Data = $this->getSessionData('step1') ?? [];
        $step2Data = $this->getSessionData('step2') ?? [];
        $errors = $this->getSessionData('errors') ?? [];
        
        $combinedData = array_merge($step1Data, $step2Data);

        // جلب خطط الاشتراك
        $plans = $this->getAvailablePlans();
        $defaultPlanId = $this->getDefaultPlanId();

        $this->renderTemplate('vendor-register-step3', [
            'step_key'         => 'step3',
            'form_data'        => $combinedData,
            'errors'           => $errors,
            'plans'            => $plans,
            'default_plan_id'  => $defaultPlanId,
            'settings'         => $this->settings,
        ]);
    }

    /**
     * التحقق من صحة خطوة معينة
     */
    private function validateStep(int $step): bool
    {
        switch ($step) {
            case 1:
                return !empty($this->getSessionData('step1'));
            case 2:
                return !empty($this->getSessionData('step1')) && !empty($this->getSessionData('step2'));
            case 3:
                return !empty($this->getSessionData('step1')) && !empty($this->getSessionData('step2'));
            default:
                return false;
        }
    }

    /**
     * إعادة التوجيه لخطوة معينة
     */
    private function redirectToStep(int $step): void
    {
        $url = add_query_arg('vmp_step', $step, get_permalink());
        wp_safe_redirect($url);
        exit;
    }

    /**
     * إعادة التوجيه للوحة التحكم
     */
    private function redirectToDashboard(): void
    {
        $dashboardUrl = $this->settings['vendor_dashboard_url'] ?? home_url('/vendor-dashboard/');
        wp_safe_redirect($dashboardUrl);
        exit;
    }

    /**
     * الحصول على خطة الاشتراك الافتراضية
     */
    private function getDefaultPlanId(): int
    {
        $planId = $this->settings['default_plan_id'] ?? 0;
        return absint($planId);
    }

    /**
     * جلب خطط الاشتراك المتاحة
     */
    private function getAvailablePlans(): array
    {
        if (!class_exists('VMP\\Services\\SubscriptionPlanService')) {
            return [];
        }

        $planService = Container::getInstance()->make(\VMP\Services\SubscriptionPlanService::class);
        if (!$planService) {
            return [];
        }

        $plans = $planService->getAllActivePlans();
        return is_array($plans) ? $plans : [];
    }

    /**
     * حفظ بيانات في الجلسة
     */
    private function saveToSession(string $key, mixed $value): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $prefix = 'vmp_vendor_reg_';
        $_SESSION[$prefix . $key] = $value;
    }

    /**
     * جلب بيانات من الجلسة
     */
    private function getSessionData(string $key): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $prefix = 'vmp_vendor_reg_';
        return $_SESSION[$prefix . $key] ?? null;
    }

    /**
     * تنظيف الجلسة
     */
    private function clearSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $prefix = 'vmp_vendor_reg_';
        foreach ($_SESSION as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * عرض قالب (template)
     */
    private function renderTemplate(string $template, array $data = []): void
    {
        extract($data);
        
        $templatePath = VMP_PLUGIN_DIR . 'public/templates/' . $template . '.php';
        
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            // fallback للقالب في مسار آخر
            $altPath = VMP_PLUGIN_DIR . 'templates/dashboard/' . $template . '.php';
            if (file_exists($altPath)) {
                include $altPath;
            } else {
                echo '<div class="vmp-error"><p>' . esc_html__('القالب غير موجود: ' . $template, 'vmp') . '</p></div>';
            }
        }
    }
}
