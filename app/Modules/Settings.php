<?php
namespace VMP\Modules;

use VMP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وحدة الإعدادات – تدير إعدادات الإضافة من لوحة المشرف
 * تدعم الإعدادات المتداخلة، وتستخدم نظام config() المركزي
 */
class Settings extends AbstractModule
{
    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * تهيئة الوحدة وتسجيل الإجراءات
     */
    public function init(): void
    {
        // [QA 2026-08-05] Phase C — تم نقل تسجيل جميع مسارات AJAX إلى RouteRegistry
        // في CoreServiceProvider (عبر SettingsController). المسارات الخمسة:
        //   vmp_admin_save_settings, vmp_admin_get_settings, vmp_mark_notice_read,
        //   vmp_mark_all_notices_read, vmp_test_email
        // الأسطر أدناه معطّلة لتجنب الازدواجية (الإبقاء للتوثيق).
        // add_action('wp_ajax_vmp_admin_save_settings', [$this, 'save_settings']);
        // add_action('wp_ajax_vmp_admin_get_settings', [$this, 'get_settings']);
        // add_action('wp_ajax_vmp_mark_notice_read', [$this, 'mark_notice_read']);
        // add_action('wp_ajax_vmp_mark_all_notices_read', [$this, 'mark_all_notices_read']);
        // add_action('wp_ajax_vmp_test_email', [$this, 'test_email']);
    }

    /**
     * حفظ الإعدادات (AJAX)
     * يتم استقبال الإعدادات كمصفوفة متداخلة وتخزينها في قاعدة البيانات
     */
    public function save_settings(): void
    {
        try {
            // ── 1. التحقق من الأمان ──
            if (!check_ajax_referer('vmp_admin_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => __('طلب غير مصرح به (nonce غير صحيح).', 'vmp')]);
            }

            if (!current_user_can('vmp_manage_settings')) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية لتعديل الإعدادات.', 'vmp')]);
            }

            // ── 2. جلب الإعدادات من الطلب ──
            $settings = isset($_POST['vmp_settings']) && is_array($_POST['vmp_settings'])
                ? wp_unslash($_POST['vmp_settings'])
                : [];

            if (empty($settings)) {
                wp_send_json_error(['message' => __('لم يتم إرسال أي إعدادات.', 'vmp')]);
            }

            // ── 3. تنقية الإعدادات بشكل متكرر ──
            $sanitized_settings = $this->sanitizeSettings($settings);

            // ── 4. دمج الإعدادات الجديدة مع القديمة (للحفاظ على القيم غير المرسلة) ──
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

            // ── 5. حفظ الإعدادات ──
            $updated = update_option('vmp_settings', $merged_settings);

            if ($updated === false && $merged_settings === $old_settings) {
                // قد تكون القيم مطابقة تماماً (لا تغيير)
                wp_send_json_success(['message' => __('الإعدادات محفوظة بالفعل.', 'vmp')]);
            }

            if ($updated === false) {
                wp_send_json_error(['message' => __('تعذر حفظ الإعدادات في قاعدة البيانات.', 'vmp')]);
            }

            // ── 6. تسجيل الحدث ──
            try {
                $this->make('event_manager')->trigger('vmp_settings_saved', $merged_settings);
            } catch (\Throwable $e) {
                error_log('[VMP][Settings] Settings saved, but event trigger failed: ' . $e->getMessage());
            }

            // ── 7. إعادة بناء التخزين المؤقت إذا لزم الأمر ──
            wp_cache_delete('vmp_settings', 'options');

            wp_send_json_success(['message' => __('تم حفظ الإعدادات بنجاح.', 'vmp')]);

        } catch (\Throwable $e) {
            try {
                $this->make('logger')->error('فشل حفظ الإعدادات', ['error' => $e->getMessage()]);
            } catch (\Throwable $logger_error) {
                error_log('[VMP][Settings] Failed saving settings: ' . $e->getMessage());
            }

            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * جلب الإعدادات الحالية (AJAX)
     * يُستخدم في واجهة المشرف لتحميل الإعدادات
     */
    public function get_settings(): void
    {
        try {
            check_ajax_referer('vmp_admin_nonce', 'nonce');

            if (!current_user_can('vmp_manage_settings')) {
                wp_send_json_error(['message' => __('غير مصرح لك.', 'vmp')]);
            }

            $settings = get_option('vmp_settings', []);

            wp_send_json_success([
                'settings' => $settings,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
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
     * الحصول على إعداد معين (دالة مساعدة للاستخدام الداخلي)
     *
     * @param string $key مفتاح الإعداد (مثل 'display.dashboard_page')
     * @param mixed $default القيمة الافتراضية إذا لم يكن موجوداً
     * @return mixed قيمة الإعداد
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = get_option('vmp_settings', []);
        $keys = explode('.', $key);
        $value = $settings;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * تحديث إعداد معين (دالة مساعدة للاستخدام الداخلي)
     *
     * @param string $key مفتاح الإعداد (مثل 'display.dashboard_page')
     * @param mixed $value القيمة الجديدة
     * @return bool نجاح أو فشل العملية
     */
    public function setSetting(string $key, $value): bool
    {
        $settings = get_option('vmp_settings', []);
        $keys = explode('.', $key);
        $target = &$settings;

        foreach ($keys as $k) {
            if (!isset($target[$k]) || !is_array($target[$k])) {
                $target[$k] = [];
            }
            $target = &$target[$k];
        }

        $target = $value;

        return update_option('vmp_settings', $settings);
    }

    /**
     * ✅ تحديد إشعار كمقروء (AJAX)
     */

    /**
     * ✅ إضافة إشعار في لوحة تحكم البائع (دالة عامة)
     * يمكن استدعاؤها من أي مكان لإضافة إشعار للبائع
     */
    public function add_vendor_dashboard_notice(int $vendor_id, string $title, string $message, string $type = "success"): void
    {
        $notices = get_user_meta($vendor_id, "vmp_dashboard_notices", true);
        if (!is_array($notices)) {
            $notices = [];
        }

        $notices[] = [
            "id" => uniqid(),
            "title" => $title,
            "message" => $message,
            "type" => $type,
            "created_at" => current_time("mysql"),
            "read" => false,
        ];

        // الاحتفاظ بأحدث 50 إشعار فقط
        if (count($notices) > 50) {
            $notices = array_slice($notices, -50);
        }

        update_user_meta($vendor_id, "vmp_dashboard_notices", $notices);
    }

    /**
     * ✅ إضافة إشعار لجميع البائعين (للإعلانات العامة)
     */
    public function add_notice_to_all_vendors(string $title, string $message, string $type = "info"): void
    {
        global $wpdb;
        $vendor_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE status = \"approved\"");
        foreach ($vendor_ids as $vendor_id) {
            $this->add_vendor_dashboard_notice((int) $vendor_id, $title, $message, $type);
        }
    }

    public function mark_notice_read(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('غير مصرح', 'vmp')]);
        }

        $vendor_id = vmp_get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $notice_id = sanitize_text_field($_POST['notice_id'] ?? '');
        if (empty($notice_id)) {
            wp_send_json_error(['message' => __('معرف الإشعار غير صالح', 'vmp')]);
        }

        $notices = get_user_meta($vendor_id, 'vmp_dashboard_notices', true);
        if (!is_array($notices)) {
            $notices = [];
        }

        foreach ($notices as &$notice) {
            if ($notice['id'] === $notice_id) {
                $notice['read'] = true;
                break;
            }
        }

        update_user_meta($vendor_id, 'vmp_dashboard_notices', $notices);

        wp_send_json_success(['message' => __('تم تحديد الإشعار كمقروء', 'vmp')]);
    }

    /**
     * ✅ تحديد جميع الإشعارات كمقروءة (AJAX)
     */
    public function mark_all_notices_read(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('غير مصرح', 'vmp')]);
        }

        $vendor_id = vmp_get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $notices = get_user_meta($vendor_id, 'vmp_dashboard_notices', true);
        if (is_array($notices)) {
            foreach ($notices as &$notice) {
                $notice['read'] = true;
            }
            update_user_meta($vendor_id, 'vmp_dashboard_notices', $notices);
        }

        wp_send_json_success(['message' => __('تم تحديد جميع الإشعارات كمقروءة', 'vmp')]);
    }

    /**
     * ✅ اختبار إعدادات البريد الإلكتروني (AJAX)
     */
    public function test_email(): void
    {
        try {
            check_ajax_referer('vmp_test_email_nonce', 'nonce');

            if (!current_user_can('vmp_manage_settings')) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية لاختبار الإعدادات.', 'vmp')]);
            }

            $settings = get_option('vmp_settings', []);
            $email_settings = $settings['email'] ?? [];

            if (empty($email_settings['use_smtp']) || $email_settings['use_smtp'] !== '1') {
                wp_send_json_error(['message' => __('SMTP غير مفعل. يرجى تفعيل "استخدام SMTP مخصص" أولاً.', 'vmp')]);
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
                wp_send_json_success(['message' => __('تم إرسال بريد الاختبار بنجاح! تحقق من بريد المشرف.', 'vmp')]);

            } catch (\Exception $e) {
                wp_send_json_error(['message' => sprintf(__('فشل إرسال البريد: %s', 'vmp'), $e->getMessage())]);
            }

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * ✅ إشعار عند استلام طلب جديد
     */
    public function on_order_placed_vendor_notice(int $vendor_order_id, int $parent_order_id, int $vendor_id): void
    {
        $this->add_vendor_dashboard_notice(
            $vendor_id,
            __("طلب جديد!", "vmp"),
            sprintf(__("لديك طلب جديد #%d", "vmp"), $parent_order_id),
            "success"
        );
    }

    /**
     * ✅ إشعار عند قبول منتج
     */
    public function on_product_approved_vendor_notice(int $vendor_product_id, int $product_id, int $vendor_id): void
    {
        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : __("منتج", "vmp");
        
        $this->add_vendor_dashboard_notice(
            $vendor_id,
            __("تم قبول منتجك", "vmp"),
            sprintf(__("تم قبول منتجك \"%s\" وهو الآن متاح للبيع.", "vmp"), $product_name),
            "success"
        );
    }

    /**
     * ✅ إشعار عند رفض منتج
     */
    public function on_product_rejected_vendor_notice(int $vendor_product_id, int $product_id, int $vendor_id, string $reason = ""): void
    {
        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : __("منتج", "vmp");
        
        $message = sprintf(__("تم رفض منتجك \"%s\".", "vmp"), $product_name);
        if ($reason) {
            $message .= " " . sprintf(__("السبب: %s", "vmp"), $reason);
        }
        
        $this->add_vendor_dashboard_notice(
            $vendor_id,
            __("تم رفض منتج", "vmp"),
            $message,
            "error"
        );
    }

    /**
     * ✅ إشعار عند الموافقة على سحب
     */
    public function on_withdrawal_approved_vendor_notice(int $withdrawal_id, int $vendor_id, float $amount): void
    {
        $this->add_vendor_dashboard_notice(
            $vendor_id,
            __("تم الموافقة على السحب", "vmp"),
            sprintf(__("تم الموافقة على طلب سحب بقيمة %s", "vmp"), wc_price($amount)),
            "success"
        );
    }

    /**
     * ✅ إشعار عند رفض سحب
     */
    public function on_withdrawal_rejected_vendor_notice(int $withdrawal_id, int $vendor_id, float $amount, string $reason = ""): void
    {
        $message = sprintf(__("تم رفض طلب سحب بقيمة %s.", "vmp"), wc_price($amount));
        if ($reason) {
            $message .= " " . sprintf(__("السبب: %s", "vmp"), $reason);
        }
        
        $this->add_vendor_dashboard_notice(
            $vendor_id,
            __("تم رفض السحب", "vmp"),
            $message,
            "error"
        );
    }

    /**
     * ✅ إشعار عند قبول البائع
     */
    public function on_vendor_approved_notice(int $vendor_id): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) return;
        
        $this->add_vendor_dashboard_notice(
            $vendor_id,
            __("تهانينا! تم قبول طلبك", "vmp"),
            sprintf(__("مرحباً %s! تم قبول طلبك كبائع. يمكنك الآن البدء في إضافة منتجاتك.", "vmp"), $vendor->store_name),
            "success"
        );
    }

    /**
     * ✅ إشعار عند رفض البائع
     */
    public function on_vendor_rejected_notice(int $vendor_id, string $reason = ""): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) return;
        
        $message = sprintf(__("نعتذر %s، تم رفض طلبك كبائع.", "vmp"), $vendor->store_name);
        if ($reason) {
            $message .= " " . sprintf(__("السبب: %s", "vmp"), $reason);
        }
        
        $this->add_vendor_dashboard_notice(
            $vendor_id,
            __("تم رفض طلب التسجيل", "vmp"),
            $message,
            "error"
        );
    }

}
