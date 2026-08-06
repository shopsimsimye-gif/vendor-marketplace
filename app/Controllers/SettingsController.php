<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Http\Requests\SaveSettingsRequest;
use VMP\Http\Requests\GetSettingsRequest;
use VMP\Http\Requests\MarkNoticeReadRequest;
use VMP\Http\Requests\MarkAllNoticesReadRequest;
use VMP\Http\Requests\TestEmailRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ErrorResponse;
use VMP\Http\Responses\ApiResponse;

/**
 * Class SettingsController
 *
 * [QA 2026-08-05] Phase C — نُقلت معالجة AJAX لوحدة Settings (الخمسة مسارات:
 * vmp_admin_save_settings / vmp_admin_get_settings / vmp_mark_notice_read /
 * vmp_mark_all_notices_read / vmp_test_email) من VMP\Modules\Settings
 * (تسجيل add_action مباشر) إلى هذا الـ Controller عبر RouteRegistry في
 * CoreServiceProvider. المنطق مطابق للنص الأصلي دون تغيير؛ تحل الـ Requests
 * مكان check_ajax_referer + فحص الصلاحيات (authorize).
 *
 * ملاحظة: الدوال العامة غير AJAX (add_vendor_dashboard_notice وغيرها) بقيت في
 * Modules\Settings لأنها تُستخدم من Modules\Notification — لم تُنقل هنا.
 *
 * @package vendor-marketplace
 */
class SettingsController extends BaseController
{
    protected \VMP\Core\Container $container;

    public function __construct(\VMP\Core\Container $container)
    {
        $this->container = $container;
    }
    /**
     * حفظ الإعدادات (AJAX).
     *
     * @param SaveSettingsRequest $request
     * @return ApiResponse
     */
    public function saveSettings(SaveSettingsRequest $request): ApiResponse
    {
        try {
            $settings = $request->get('vmp_settings');
            if (!is_array($settings) || empty($settings)) {
                return new ErrorResponse(message: __('لم يتم إرسال أي إعدادات.', 'vmp'), statusCode: 400);
            }

            // تنقية الإعدادات بشكل متكرر
            $sanitized_settings = $this->sanitizeSettings($settings);

            // دمج الإعدادات الجديدة مع القديمة (للحفاظ على القيم غير المرسلة)
            $old_settings = get_option('vmp_settings', []);
            $old_settings = is_array($old_settings) ? $old_settings : [];

            if (
                isset($sanitized_settings['email']['smtp_password'])
                && $sanitized_settings['email']['smtp_password'] === ''
                && !empty($old_settings['email']['smtp_password'])
            ) {
                unset($sanitized_settings['email']['smtp_password']);
            }

            $sanitized_settings = $this->syncRegistrationMessageSettings($sanitized_settings);

            $merged_settings = $this->mergeSettings($old_settings, $sanitized_settings);

            // حفظ الإعدادات
            $updated = update_option('vmp_settings', $merged_settings);

            if ($updated === false && $merged_settings === $old_settings) {
                // قد تكون القيم مطابقة تماماً (لا تغيير)
                return new SuccessResponse(data: ['message' => __('الإعدادات محفوظة بالفعل.', 'vmp')]);
            }

            if ($updated === false) {
                return new ErrorResponse(message: __('تعذر حفظ الإعدادات في قاعدة البيانات.', 'vmp'), statusCode: 500);
            }

            // تسجيل الحدث
            try {
                $this->eventManager()->trigger('vmp_settings_saved', $merged_settings);
            } catch (\Throwable $e) {
                error_log('[VMP][Settings] Settings saved, but event trigger failed: ' . $e->getMessage());
            }

            // إعادة بناء التخزين المؤقت إذا لزم الأمر
            wp_cache_delete('vmp_settings', 'options');

            return new SuccessResponse(data: ['message' => __('تم حفظ الإعدادات بنجاح.', 'vmp')]);

        } catch (\Throwable $e) {
            try {
                $this->logger()->error('فشل حفظ الإعدادات', ['error' => $e->getMessage()]);
            } catch (\Throwable $logger_error) {
                error_log('[VMP][Settings] Failed saving settings: ' . $e->getMessage());
            }

            return new ErrorResponse(message: $e->getMessage(), statusCode: 500);
        }
    }

    /**
     * جلب الإعدادات الحالية (AJAX).
     *
     * @param GetSettingsRequest $request
     * @return ApiResponse
     */
    public function getSettings(GetSettingsRequest $request): ApiResponse
    {
        try {
            $settings = get_option('vmp_settings', []);

            return new SuccessResponse(data: [
                'settings' => $settings,
            ]);

        } catch (\Exception $e) {
            return new ErrorResponse(message: $e->getMessage(), statusCode: 500);
        }
    }

    /**
     * تحديد إشعار كمقروء (AJAX) — لوحة البائع.
     *
     * @param MarkNoticeReadRequest $request
     * @return ApiResponse
     */
    public function markNoticeRead(MarkNoticeReadRequest $request): ApiResponse
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new ErrorResponse(message: __('يجب تسجيل الدخول أولاً.', 'vmp'), statusCode: 404);
        }

        $notice_id = sanitize_text_field((string) ($_POST['notice_id'] ?? ''));
        if (empty($notice_id)) {
            return new ErrorResponse(message: __('معرف الإشعار غير صالح', 'vmp'), statusCode: 400);
        }

        $notices = get_user_meta($user_id, 'vmp_dashboard_notices', true);
        if (!is_array($notices)) {
            $notices = [];
        }

        foreach ($notices as &$notice) {
            if ($notice['id'] === $notice_id) {
                $notice['read'] = true;
                break;
            }
        }

        update_user_meta($user_id, 'vmp_dashboard_notices', $notices);

        return new SuccessResponse(data: ['message' => __('تم تحديد الإشعار كمقروء', 'vmp')]);
    }

    /**
     * تحديد جميع الإشعارات كمقروءة (AJAX) — لوحة البائع.
     *
     * @param MarkAllNoticesReadRequest $request
     * @return ApiResponse
     */
    public function markAllNoticesRead(MarkAllNoticesReadRequest $request): ApiResponse
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new ErrorResponse(message: __('يجب تسجيل الدخول أولاً.', 'vmp'), statusCode: 404);
        }

        $notices = get_user_meta($user_id, 'vmp_dashboard_notices', true);
        if (is_array($notices)) {
            foreach ($notices as &$notice) {
                $notice['read'] = true;
            }
            update_user_meta($user_id, 'vmp_dashboard_notices', $notices);
        }

        return new SuccessResponse(data: ['message' => __('تم تحديد جميع الإشعارات كمقروءة', 'vmp')]);
    }

    /**
     * اختبار إعدادات البريد الإلكتروني (AJAX).
     *
     * @param TestEmailRequest $request
     * @return ApiResponse
     */
    public function testEmail(TestEmailRequest $request): ApiResponse
    {
        try {
            $settings = get_option('vmp_settings', []);
            $email_settings = $settings['email'] ?? [];

            if (empty($email_settings['use_smtp']) || $email_settings['use_smtp'] !== '1') {
                return new ErrorResponse(message: __('SMTP غير مفعل. يرجى تفعيل "استخدام SMTP مخصص" أولاً.', 'vmp'), statusCode: 400);
            }

            $from_name = !empty($email_settings['from_name']) ? $email_settings['from_name'] : get_bloginfo('name');
            $from_email = !empty($email_settings['from_email']) ? $email_settings['from_email'] : get_option('admin_email');
            $to_email = get_option('admin_email');

            // إعداد PHPMailer للـ SMTP
            $phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $phpmailer->isSMTP();
                $phpmailer->Host = $email_settings['smtp_host'];
                $phpmailer->Port = (int) ($email_settings['smtp_port'] ?? 587);
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $email_settings['smtp_username'];
                $phpmailer->Password = $email_settings['smtp_password'];

                $encryption = $email_settings['smtp_encryption'] ?? 'tls';
                if ($encryption === 'ssl') {
                    $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls') {
                    $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $phpmailer->SMTPSecure = false;
                }

                $phpmailer->setFrom($from_email, $from_name);
                $phpmailer->addAddress($to_email);
                $phpmailer->isHTML(true);
                $phpmailer->Subject = sprintf(__('[%s] بريد اختبار من Vendor Marketplace', 'vmp'), get_bloginfo('name'));
                $phpmailer->Body = sprintf(
                    __('<p>هذا بريد إلكتروني تجريبي للتحقق من إعدادات SMTP.</p><p>تم إرساله في: %s</p><p>إعدادات المرسل: %s <%s></p>', 'vmp'),
                    current_time('mysql'),
                    esc_html($from_name),
                    esc_html($from_email)
                );

                $phpmailer->send();
                return new SuccessResponse(data: ['message' => __('تم إرسال بريد الاختبار بنجاح! تحقق من بريد المشرف.', 'vmp')]);

            } catch (\Exception $e) {
                return new ErrorResponse(message: sprintf(__('فشل إرسال البريد: %s', 'vmp'), $e->getMessage()), statusCode: 500);
            }

        } catch (\Exception $e) {
            return new ErrorResponse(message: $e->getMessage(), statusCode: 500);
        }
    }

    /**
     * تنقية الإعدادات بشكل متكرر (يدعم المصفوفات المتداخلة)
     *
     * @param array $settings مصفوفة الإعدادات
     * @param string $context سياق التنقية (للمساعدة في التصحيح)
     * @return array مصفوفة الإعدادات المنقاة
     */
    private function sanitizeSettings(array $settings, string $context = ''): array
    {
        $result = [];

        foreach ($settings as $key => $value) {
            $sanitized_key = sanitize_key($key);

            if (is_array($value)) {
                // تنقية المصفوفات المتداخلة
                $result[$sanitized_key] = $this->sanitizeSettings($value, $context . '.' . $sanitized_key);
            } elseif (is_string($value)) {
                if (strpos($context, '.messages') !== false) {
                    $result[$sanitized_key] = wp_kses_post($value);
                    continue;
                }

                // تنقية النصوص
                $result[$sanitized_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                // تنقية الأرقام
                $result[$sanitized_key] = (float) $value;
            } elseif (is_bool($value)) {
                // القيم المنطقية
                $result[$sanitized_key] = (bool) $value;
            } else {
                // القيم الأخرى (مثل null)
                $result[$sanitized_key] = $value;
            }
        }

        return $result;
    }

    /**
     * دمج الإعدادات الجديدة مع القديمة للحفاظ على القيم غير المرسلة
     *
     * @param array $old الإعدادات القديمة
     * @param array $new الإعدادات الجديدة
     * @return array الإعدادات المدمجة
     */
    private function mergeSettings(array $old, array $new): array
    {
        $merged = $old;

        foreach ($new as $key => $value) {
            if (is_array($value) && isset($old[$key]) && is_array($old[$key])) {
                // دمج المصفوفات المتداخلة
                $merged[$key] = $this->mergeSettings($old[$key], $value);
            } else {
                // استبدال القيم الجديدة
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Keep legacy message fields and registration service fields in sync.
     */
    private function syncRegistrationMessageSettings(array $settings): array
    {
        $map = [
            'register_success'  => 'register_success_message',
            'pending_review'    => 'pending_approval_message',
            'register_approved' => 'approval_message',
            'register_rejected' => 'rejection_message',
        ];

        foreach ($map as $message_key => $registration_key) {
            if (!isset($settings['messages'][$message_key]) || $settings['messages'][$message_key] === '') {
                continue;
            }

            if (!isset($settings['registration']) || !is_array($settings['registration'])) {
                $settings['registration'] = [];
            }

            $settings['registration'][$registration_key] = $settings['messages'][$message_key];
        }

        return $settings;
    }

    /**
     * الحصول على EventManager من الحاوية.
     */
    private function eventManager(): object
    {
        return $this->container->make('event_manager');
    }

    /**
     * الحصول على Logger من الحاوية.
     */
    private function logger(): object
    {
        return $this->container->make('logger');
    }
}
