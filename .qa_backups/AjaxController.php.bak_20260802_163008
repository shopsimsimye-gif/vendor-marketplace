<?php
namespace VMP\Http\Controllers\Ajax;

defined('ABSPATH') || exit;

use VMP\Services\VendorRegistrationService;
use VMP\DTO\RegisterVendorDTO;
use VMP\Http\Requests\RegisterVendorRequest;
use VMP\Core\Container;

/**
 * AjaxController — معالج طلبات AJAX لتسجيل البائعين
 *
 * يتعامل مع طلبات الخطوات الثلاث، التحقق من السلاگ، والبريد الإلكتروني
 *
 * @package VMP\Http\Controllers\Ajax
 * @since 2.0.0
 */
class AjaxController
{
    private VendorRegistrationService $registrationService;

    private function t(string $text): string
    {
        return function_exists('__') ? (string) call_user_func('__', $text, 'vmp') : $text;
    }

    private function verifyNonce(string $nonce, string $action): bool
    {
        return function_exists('wp_verify_nonce') ? (bool) call_user_func('wp_verify_nonce', $nonce, $action) : true;
    }

    private function sendJsonSuccess(mixed $data): void
    {
        if (function_exists('wp_send_json_success')) {
            call_user_func('wp_send_json_success', $data);
            return;
        }

        header('Content-Type: application/json');
        echo $this->wpJsonEncode(['success' => true, 'data' => $data]);
        exit;
    }

    private function sendJsonError(mixed $data, int $statusCode = 200): void
    {
        if (function_exists('wp_send_json_error')) {
            call_user_func('wp_send_json_error', $data, $statusCode);
            return;
        }

        header('Content-Type: application/json');
        echo $this->wpJsonEncode(['success' => false, 'data' => $data]);
        exit;
    }

    private function wpJsonEncode(mixed $data): string
    {
        return function_exists('wp_json_encode')
            ? (string) call_user_func('wp_json_encode', $data)
            : (string) json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function homeUrl(string $path = ''): string
    {
        return function_exists('home_url') ? (string) call_user_func('home_url', $path) : $path;
    }

    private function sanitizeSlug(string $value): string
    {
        return function_exists('sanitize_title')
            ? (string) call_user_func('sanitize_title', $value)
            : strtolower(preg_replace('/[^a-z0-9-]+/', '-', trim($value)));
    }

    private function sanitizeEmail(string $value): string
    {
        return function_exists('sanitize_email')
            ? (string) call_user_func('sanitize_email', $value)
            : filter_var($value, FILTER_SANITIZE_EMAIL);
    }

    private function sanitizeText(string $value): string
    {
        return function_exists('sanitize_text_field')
            ? (string) call_user_func('sanitize_text_field', $value)
            : trim($value);
    }

    private function absInt(mixed $value): int
    {
        return function_exists('absint') ? (int) call_user_func('absint', $value) : (int) $value;
    }

    private function requestValue(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $default;
        return function_exists('wp_unslash') ? call_user_func('wp_unslash', $value) : $value;
    }

    private function addWpAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (function_exists('add_action')) {
            call_user_func('add_action', $hook, $callback, $priority, $acceptedArgs);
        }
    }

    private function isUserLoggedIn(): bool
    {
        return function_exists('is_user_logged_in') ? (bool) call_user_func('is_user_logged_in') : false;
    }

    private function getCurrentWpUser(): mixed
    {
        return function_exists('wp_get_current_user') ? call_user_func('wp_get_current_user') : null;
    }

    private function applyWpFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return function_exists('apply_filters') ? call_user_func('apply_filters', $hook, $value, ...$args) : $value;
    }

    private function doWpAction(string $hook, mixed ...$args): void
    {
        if (function_exists('do_action')) {
            call_user_func('do_action', $hook, ...$args);
        }
    }

    private function isEmailValue(string $value): bool
    {
        return function_exists('is_email') ? (bool) call_user_func('is_email', $value) : filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isWpErrorValue(mixed $value): bool
    {
        return function_exists('is_wp_error') ? (bool) call_user_func('is_wp_error', $value) : false;
    }

    public function __construct()
    {
        $container = Container::getInstance();
        $this->registrationService = $container->make(VendorRegistrationService::class);
    }

    /**
     * تسجيل مسارات AJAX
     */
    public function registerAjaxActions(): void
    {
        // الخطوة 1: إنشاء الحساب
        $this->addWpAction('wp_ajax_vmp_vendor_register_step1', [$this, 'handleStep1']);
        $this->addWpAction('wp_ajax_nopriv_vmp_vendor_register_step1', [$this, 'handleStep1']);

        // الخطوة 2: بيانات المتجر
        $this->addWpAction('wp_ajax_vmp_vendor_register_step2', [$this, 'handleStep2']);
        $this->addWpAction('wp_ajax_nopriv_vmp_vendor_register_step2', [$this, 'handleStep2']);

        // الخطوة 3: الإرسال النهائي
        $this->addWpAction('wp_ajax_vmp_vendor_register_step3', [$this, 'handleStep3']);
        $this->addWpAction('wp_ajax_nopriv_vmp_vendor_register_step3', [$this, 'handleStep3']);

        // التحقق من توفر رابط المتجر (slug)
        $this->addWpAction('wp_ajax_vmp_check_store_slug', [$this, 'checkStoreSlug']);
        $this->addWpAction('wp_ajax_nopriv_vmp_check_store_slug', [$this, 'checkStoreSlug']);

        // التحقق من توفر البريد الإلكتروني
        $this->addWpAction('wp_ajax_vmp_check_email', [$this, 'checkEmail']);
        $this->addWpAction('wp_ajax_nopriv_vmp_check_email', [$this, 'checkEmail']);

        // رفع ملفات الميديا
        $this->addWpAction('wp_ajax_vmp_upload_media', [$this, 'handleMediaUpload']);
        $this->addWpAction('wp_ajax_nopriv_vmp_upload_media', [$this, 'handleMediaUpload']);

        // التحقق من حالة الطلب
        $this->addWpAction('wp_ajax_vmp_check_request_status', [$this, 'checkRequestStatus']);
        $this->addWpAction('wp_ajax_nopriv_vmp_check_request_status', [$this, 'checkRequestStatus']);
    }

    /**
     * معالجة الخطوة 1 (إنشاء الحساب)
     */
    public function handleStep1(): void
    {
        // التحقق من nonce
        if (!$this->verifyNonce((string) $this->requestValue('nonce', ''), 'vmp_vendor_register_step1')) {
            $this->sendError($this->t('رمز التحقق غير صالح. يرجى تحديث الصفحة والمحاولة مرة أخرى.'));
            return;
        }

        // بناء بيانات الطلب
        $request = RegisterVendorRequest::fromPost('vmp_vendor_register_step1', 'vmp_step1_nonce');

        if (!$request->validate()) {
            $this->sendError($request->firstError(), [
                'errors' => $request->errors(),
                'step' => 1,
            ]);
            return;
        }

        $dto = $request->toDTO();
        $cleanData = $dto->toArray();
        
        // حفظ في الجلسة
        $this->saveToSession('step1', $cleanData);

        // إذا كان المستخدم مسجلاً، إضافة معرفه
        if ($this->isUserLoggedIn()) {
            $user = $this->getCurrentWpUser();
            if (!$user) {
                $this->sendError($this->t('تعذر قراءة بيانات المستخدم الحالي.'));
                return;
            }
            $this->saveToSession('user_id', $user->ID);
            $dto->user_id = $user->ID;
            
            // تحديث الاسم الكامل إذا تغير
            if (!empty($dto->full_name)) {
                $this->registrationService->updateUserFullName($user->ID, $dto->full_name);
            }
        }

        $this->sendSuccess([
            'message' => $this->t('تم حفظ البيانات، جاري الانتقال...'),
            'redirect_to' => '#step2',
            'data' => $cleanData,
        ]);
    }

    /**
     * معالجة الخطوة 2 (بيانات المتجر)
     */
    public function handleStep2(): void
    {
        if (!$this->verifyNonce((string) $this->requestValue('nonce', ''), 'vmp_vendor_register_step2')) {
            $this->sendError($this->t('رمز التحقق غير صالح.'));
            return;
        }

        $step1Data = $this->getFromSession('step1');
        if (!$step1Data) {
            $this->sendError($this->t('انتهت صلاحية الجلسة، يرجى البدء من جديد.'), [
                'code' => 'session_expired',
                'step' => 1,
            ]);
            return;
        }

        // دمج بيانات الخطوة 1 مع الخطوة 2
        $allData = array_merge($step1Data, $_POST);
        
        $request = RegisterVendorRequest::fromPost('vmp_vendor_register_step2', 'vmp_step2_nonce');
        $request->setData($allData);

        if (!$request->validate()) {
            $this->sendError($request->firstError(), [
                'errors' => $request->errors(),
                'step' => 2,
            ]);
            return;
        }

        $dto = $request->toDTO();
        $cleanData = $dto->toArray();
        
        $this->saveToSession('step2', $cleanData);

        $this->sendSuccess([
            'message' => $this->t('تم حفظ بيانات المتجر، جاري الانتقال...'),
            'redirect_to' => '#step3',
            'data' => $cleanData,
        ]);
    }

    /**
     * معالجة الخطوة 3 (الإرسال النهائي)
     */
    public function handleStep3(): void
    {
        if (!$this->verifyNonce((string) $this->requestValue('nonce', ''), 'vmp_vendor_register_step3')) {
            $this->sendError($this->t('رمز التحقق غير صالح.'));
            return;
        }

        $step1Data = $this->getFromSession('step1');
        $step2Data = $this->getFromSession('step2');

        if (!$step1Data || !$step2Data) {
            $this->sendError($this->t('انتهت صلاحية الجلسة، يرجى البدء من جديد.'), [
                'code' => 'session_expired',
                'step' => 1,
            ]);
            return;
        }

        // دمج جميع البيانات
        $allData = array_merge($step1Data, $step2Data, $_POST);
        $allData['user_id'] = $step1Data['user_id'] ?? 0;

        $request = RegisterVendorRequest::fromPost('vmp_vendor_register_step3', 'vmp_step3_nonce');
        $request->setData($allData);

        if (!$request->validate()) {
            $this->sendError($request->firstError(), [
                'errors' => $request->errors(),
                'step' => 3,
            ]);
            return;
        }

        $dto = $request->toDTO();
        $requestData = $dto->getRequestData();

        // إنشاء الطلب
        $requestId = $this->registrationService->createRequest($requestData);

        if (!$requestId) {
            $this->sendError($this->t('فشل إنشاء الطلب، يرجى المحاولة مرة أخرى.'));
            return;
        }

        // تنظيف الجلسة
        $this->clearRegisterSession();

        // رسالة النجاح
        $successMessage = function_exists('apply_filters')
            ? $this->applyWpFilters('vmp_vendor_register_success_message',
                $this->registrationService->getSetting('register_success_message',
                    $this->t('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة من قبل الإدارة.')
                ),
                $requestId
            )
            : $this->registrationService->getSetting('register_success_message',
                $this->t('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة من قبل الإدارة.')
            );

        // رابط التوجيه
        $redirectUrl = $this->registrationService->getSetting('redirect_after_submit', $this->homeUrl('/my-account/'));

        // إطلاق حدث للإشعارات
        $this->doWpAction('vmp_vendor_request_submitted', $requestId, $dto->user_id, $requestData);

        $this->sendSuccess([
            'message' => $successMessage,
            'request_id' => $requestId,
            'redirect_to' => $redirectUrl,
        ]);
    }

    /**
     * التحقق من توفر رابط المتجر (slug)
     */
    public function checkStoreSlug(): void
    {
        if (!$this->verifyNonce((string) $this->requestValue('nonce', ''), 'vmp_vendor_check_slug')) {
            $this->sendJsonError(['message' => 'Invalid nonce'], 403);
            return;
        }

        $slug = $this->sanitizeSlug((string) $this->requestValue('slug', ''));
        $excludeUserId = $this->absInt($this->requestValue('exclude_user_id', 0));

        if (empty($slug)) {
            $this->sendJsonError(['message' => $this->t('slug فارغ')]);
            return;
        }

        if (strlen($slug) < 3) {
            $this->sendJsonSuccess([
                'available' => false,
                'slug' => $slug,
                'message' => $this->t('الحد الأدنى 3 أحرف'),
            ]);
            return;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $this->sendJsonSuccess([
                'available' => false,
                'slug' => $slug,
                'message' => $this->t('أحرف وأرقام وشرطات فقط'),
            ]);
            return;
        }

        $exists = $this->registrationService->slugExists($slug, $excludeUserId);

        $this->sendJsonSuccess([
            'available' => !$exists,
            'slug' => $slug,
            'message' => $exists ? $this->t('الرابط مستخدم مسبقاً') : $this->t('الرابط متاح'),
        ]);
    }

    /**
     * التحقق من توفر البريد الإلكتروني
     */
    public function checkEmail(): void
    {
        if (!$this->verifyNonce((string) $this->requestValue('nonce', ''), 'vmp_vendor_check_email')) {
            $this->sendJsonError(['message' => 'Invalid nonce'], 403);
            return;
        }

        $email = $this->sanitizeEmail((string) $this->requestValue('email', ''));
        $excludeUserId = $this->absInt($this->requestValue('exclude_user_id', 0));

        if (empty($email)) {
            $this->sendJsonError(['message' => $this->t('بريد إلكتروني فارغ')]);
            return;
        }

        if (!$this->isEmailValue($email)) {
            $this->sendJsonSuccess([
                'available' => false,
                'email' => $email,
                'message' => $this->t('بريد إلكتروني غير صحيح'),
            ]);
            return;
        }

        $exists = $this->registrationService->emailExists($email, $excludeUserId);

        $this->sendJsonSuccess([
            'available' => !$exists,
            'email' => $email,
            'message' => $exists ? $this->t('هذا البريد الإلكتروني مسجّل مسبقاً') : $this->t('البريد الإلكتروني متاح'),
        ]);
    }

    /**
     * رفع ملفات الميديا (شعار، غلاف، رخصة)
     */
    public function handleMediaUpload(): void
    {
        if (!$this->verifyNonce((string) $this->requestValue('nonce', ''), 'vmp_vendor_media_upload')) {
            $this->sendJsonError(['message' => 'Invalid nonce'], 403);
            return;
        }

        if (!isset($_FILES['file'])) {
            $this->sendJsonError(['message' => $this->t('لم يتم رفع ملف')]);
            return;
        }

        $type = $this->sanitizeText($_POST['type'] ?? 'logo');
        $result = $this->registrationService->handleMediaUpload($_FILES['file'], $type);

        if ($this->isWpErrorValue($result)) {
            $this->sendJsonError(['message' => $result->get_error_message()]);
            return;
        }

        $this->sendJsonSuccess($result);
    }

    /**
     * التحقق من حالة طلب المستخدم الحالي
     */
    public function checkRequestStatus(): void
    {
        if (!$this->verifyNonce((string) $this->requestValue('nonce', ''), 'vmp_vendor_registration_nonce')) {
            $this->sendJsonError(['code' => 'invalid_nonce'], 403);
            return;
        }

        if (!$this->isUserLoggedIn()) {
            $this->sendJsonError(['code' => 'not_logged_in']);
            return;
        }

        $user = $this->getCurrentWpUser();
        if (!$user) {
            $this->sendJsonError(['code' => 'user_not_found']);
            return;
        }
        $request = $this->registrationService->getPendingRequestByUser($user->ID);
        $vendor = $this->registrationService->getVendorByUser($user->ID);

        if ($vendor) {
            $this->sendJsonSuccess([
                'status' => 'approved',
                'vendor_id' => $vendor->id,
                'message' => $this->registrationService->getSetting('approval_message',
                    $this->t('تهانينا! تم قبول طلبك وأصبحت بائعاً.')
                ),
            ]);
            return;
        }

        if ($request) {
            $message = ($request->status === 'rejected')
                ? $this->registrationService->getSetting('rejection_message',
                    $this->t('تم رفض طلبك. السبب: ') . $request->admin_notes
                )
                : $this->registrationService->getSetting('pending_approval_message',
                    $this->t('طلبك قيد المراجعة، يرجى الانتظار.')
                );

            $this->sendJsonSuccess([
                'status' => $request->status,
                'request_id' => $request->id,
                'message' => $message,
                'admin_notes' => $request->admin_notes ?? '',
            ]);
            return;
        }

        $this->sendJsonSuccess(['status' => 'none']);
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
    private function getFromSession(string $key): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $prefix = 'vmp_vendor_reg_';
        return $_SESSION[$prefix . $key] ?? null;
    }

    /**
     * مسح الجلسة
     */
    private function clearRegisterSession(): void
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
     * إرسال استجابة نجاح
     */
    private function sendSuccess(array $data): void
    {
        $this->sendJsonSuccess($data);
    }

    /**
     * إرسال استجابة خطأ
     */
    private function sendError(string $message, array $extra = []): void
    {
        $this->sendJsonError(array_merge([
            'message' => $message,
        ], $extra));
    }
}
