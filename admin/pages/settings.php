<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من الصلاحية ──
if (!current_user_can('vmp_manage_settings')) {
    wp_die(__('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'vmp'));
}

if (!function_exists('vmp_admin_sanitize_settings_payload')) {
    /**
     * Sanitize nested settings while preserving allowed HTML in message fields.
     */
    function vmp_admin_sanitize_settings_payload(array $settings, string $context = ''): array
    {
        $result = [];

        foreach ($settings as $key => $value) {
            $sanitized_key = sanitize_key((string) $key);

            if (is_array($value)) {
                $result[$sanitized_key] = vmp_admin_sanitize_settings_payload($value, $context . '.' . $sanitized_key);
                continue;
            }

            if (is_string($value)) {
                $result[$sanitized_key] = strpos($context, '.messages') !== false
                    ? wp_kses_post($value)
                    : sanitize_text_field($value);
                continue;
            }

            $result[$sanitized_key] = $value;
        }

        return $result;
    }
}

if (!function_exists('vmp_admin_merge_settings_payload')) {
    /**
     * Recursively merge new settings into existing settings.
     */
    function vmp_admin_merge_settings_payload(array $old, array $new): array
    {
        $merged = $old;

        foreach ($new as $key => $value) {
            if (is_array($value) && isset($old[$key]) && is_array($old[$key])) {
                $merged[$key] = vmp_admin_merge_settings_payload($old[$key], $value);
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}

if (!function_exists('vmp_admin_sync_registration_message_settings')) {
    /**
     * Keep legacy message fields and registration service fields in sync.
     */
    function vmp_admin_sync_registration_message_settings(array $settings): array
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
}

// ── حفظ احتياطي عند إرسال النموذج بدون AJAX ──
$settings_notice = null;
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['vmp_settings'])
    && is_array($_POST['vmp_settings'])
) {
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));

    if (!wp_verify_nonce($nonce, 'vmp_admin_nonce')) {
        $settings_notice = [
            'type'    => 'error',
            'message' => __('طلب غير مصرح به (nonce غير صحيح).', 'vmp'),
        ];
    } else {
        $old_settings = get_option('vmp_settings', []);
        $new_settings = vmp_admin_sanitize_settings_payload(wp_unslash($_POST['vmp_settings']));
        $new_settings = vmp_admin_sync_registration_message_settings($new_settings);

        if (
            isset($new_settings['email']['smtp_password'])
            && $new_settings['email']['smtp_password'] === ''
            && !empty($old_settings['email']['smtp_password'])
        ) {
            unset($new_settings['email']['smtp_password']);
        }

        $merged_settings = vmp_admin_merge_settings_payload(
            is_array($old_settings) ? $old_settings : [],
            $new_settings
        );

        $updated = update_option('vmp_settings', $merged_settings);
        wp_cache_delete('vmp_settings', 'options');

        $settings_notice = [
            'type'    => ($updated || $merged_settings === $old_settings) ? 'success' : 'error',
            'message' => ($updated || $merged_settings === $old_settings)
                ? __('تم حفظ الإعدادات بنجاح.', 'vmp')
                : __('تعذر حفظ الإعدادات في قاعدة البيانات.', 'vmp'),
        ];
    }
}

// ── جلب الإعدادات ──
$settings = get_option('vmp_settings', []);
$general  = $settings['general'] ?? [];
$finance  = $settings['finance'] ?? [];
$display  = $settings['display'] ?? [];
$messages = $settings['messages'] ?? [];
$email    = $settings['email'] ?? [];

$messages['register_success'] = $messages['register_success'] ?? ($settings['registration']['register_success_message'] ?? '');
$messages['pending_review'] = $messages['pending_review'] ?? ($settings['registration']['pending_approval_message'] ?? '');
$messages['register_approved'] = $messages['register_approved'] ?? ($settings['registration']['approval_message'] ?? '');
$messages['register_rejected'] = $messages['register_rejected'] ?? ($settings['registration']['rejection_message'] ?? '');
?>

<div class="wrap vmp-admin-wrap">
    <div class="vmp-admin-header">
        <h1><?php _e('إعدادات Vendor Marketplace', 'vmp'); ?></h1>
    </div>

    <!-- رسائل الإشعارات -->
    <div id="vmp-settings-notice" style="<?php echo $settings_notice ? '' : 'display:none;'; ?>" class="notice <?php echo $settings_notice ? 'notice-' . esc_attr($settings_notice['type']) : ''; ?>">
        <?php if ($settings_notice) : ?>
            <p><?php echo esc_html($settings_notice['message']); ?></p>
        <?php endif; ?>
    </div>

    <!-- التبويبات -->
    <div class="vmp-admin-tabs">
        <a href="#tab-general" class="vmp-admin-tab active" data-tab="general"><?php _e('عام', 'vmp'); ?></a>
        <a href="#tab-finance" class="vmp-admin-tab" data-tab="finance"><?php _e('المالية', 'vmp'); ?></a>
        <a href="#tab-email" class="vmp-admin-tab" data-tab="email"><?php _e('البريد الإلكتروني', 'vmp'); ?></a>
        <a href="#tab-display" class="vmp-admin-tab" data-tab="display"><?php _e('المظهر والصفحات', 'vmp'); ?></a>
        <a href="#tab-registration" class="vmp-admin-tab" data-tab="registration"><?php _e('تسجيل البائعين', 'vmp'); ?></a>
    </div>

    <div class="vmp-card">
        <div class="vmp-card-body">
            <form id="vmp-settings-form" method="post" data-action="vmp_admin_save_settings" data-ajax="0">
                <?php wp_nonce_field("vmp_admin_nonce", "nonce"); ?>
                
                <!-- حقول مخفية للـ checkboxes غير المحددة -->
                <input type="hidden" name="vmp_settings[general][enable_registration]" value="0">
                <input type="hidden" name="vmp_settings[general][auto_approve_vendors]" value="0">
                <input type="hidden" name="vmp_settings[general][auto_approve_products]" value="0">
                <input type="hidden" name="vmp_settings[finance][enable_subscriptions]" value="0">
                <input type="hidden" name="vmp_settings[email][from_name]" value="">
                <input type="hidden" name="vmp_settings[email][from_email]" value="">
                <input type="hidden" name="vmp_settings[email][smtp_host]" value="">
                <input type="hidden" name="vmp_settings[email][smtp_port]" value="">
                <input type="hidden" name="vmp_settings[email][smtp_username]" value="">
                <input type="hidden" name="vmp_settings[email][smtp_password]" value="">
                <input type="hidden" name="vmp_settings[email][smtp_encryption]" value="">
                <input type="hidden" name="vmp_settings[email][use_smtp]" value="0">
                <input type="hidden" name="vmp_settings[email][notify_new_vendor]" value="0">
                <input type="hidden" name="vmp_settings[email][notify_new_order]" value="0">
                <input type="hidden" name="vmp_settings[email][notify_withdrawal_request]" value="0">
                <input type="hidden" name="vmp_settings[email][notify_order_completed]" value="0">
                <input type="hidden" name="vmp_settings[email][notify_product_approved]" value="0">
                <input type="hidden" name="vmp_settings[email][notify_product_rejected]" value="0">
                <input type="hidden" name="vmp_settings[registration][require_email_verification]" value="0">
                <input type="hidden" name="vmp_settings[registration][auto_login_after_register]" value="0">
                <input type="hidden" name="vmp_settings[registration][require_strong_password]" value="0">
                <input type="hidden" name="vmp_settings[registration][require_store_address]" value="0">
                <input type="hidden" name="vmp_settings[registration][require_store_description]" value="0">
                <input type="hidden" name="vmp_settings[registration][allow_custom_domain]" value="0">
                <input type="hidden" name="vmp_settings[registration][require_whatsapp]" value="0">
                <input type="hidden" name="vmp_settings[registration][require_plan_selection]" value="0">
                <input type="hidden" name="vmp_settings[registration][show_plan_features]" value="0">
                <input type="hidden" name="vmp_settings[registration][default_free_plan]" value="0">
                <input type="hidden" name="vmp_settings[registration][save_progress]" value="0">
                <input type="hidden" name="vmp_settings[registration][show_progress_bar]" value="0">
                <input type="hidden" name="vmp_settings[registration][terms_required]" value="0">
                <input type="hidden" name="vmp_settings[registration][send_welcome_email]" value="0">
                <input type="hidden" name="vmp_settings[registration][enable_session_resume]" value="0">
                <input type="hidden" name="vmp_settings[registration][log_registration_attempts]" value="0">
                <input type="hidden" name="vmp_settings[registration][send_admin_notification]" value="0">

                <!-- تبويب الإعدادات العامة -->
                <div id="tab-general" class="vmp-tab-content">
                    <h2><?php _e('الإعدادات العامة', 'vmp'); ?></h2>
                    <table class="form-table">

                        <tr>
                            <th scope="row"><label><?php _e('رسالة تأكيد التسجيل', 'vmp'); ?></label></th>
                            <td>
                                <textarea name="vmp_settings[messages][register_success]" rows="4" class="large-text"><?php echo esc_textarea($messages['register_success'] ?? ''); ?></textarea>
                                <p class="description"><?php _e('الرسالة التي يستقبلها المستخدم بعد إكمال التسجيل (يمكن استخدام HTML).', 'vmp'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label><?php _e('الموافقة التلقائية على البائعين', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[general][auto_approve_vendors]" value="1" <?php checked(($general['auto_approve_vendors'] ?? '') === '1'); ?>>
                                    <?php _e('الموافقة التلقائية على البائعين الجدد', 'vmp'); ?>
                                </label>
                            </td>
                        </tr>

                        <!-- ✅ ميزة النشر بدون مراجعة -->
                        <tr>
                            <th scope="row"><label><?php _e('الموافقة على المنتجات', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[general][auto_approve_products]" value="1" <?php checked(($general['auto_approve_products'] ?? '') === '1'); ?>>
                                    <?php _e('الموافقة التلقائية على المنتجات الجديدة المضافة بواسطة البائعين (نشر بدون مراجعة)', 'vmp'); ?>
                                </label>
                                <p class="description"><?php _e('عند تفعيل هذا الخيار، سيتم نشر منتجات البائعين مباشرة دون الحاجة إلى موافقة المشرف.', 'vmp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- تبويب المالية -->
                <div id="tab-finance" class="vmp-tab-content" style="display:none;">
                    <h2><?php _e('الإعدادات المالية والعمولات', 'vmp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('نسبة العمولة الافتراضية (%)', 'vmp'); ?></label></th>
                            <td>
                                <input type="number" step="0.01" name="vmp_settings[finance][default_commission]" class="regular-text" value="<?php echo esc_attr($finance['default_commission'] ?? ''); ?>">
                                <p class="description"><?php _e('تطبق في حال لم يمتلك البائع خطة اشتراك خاصة.', 'vmp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('الحد الأدنى للسحب', 'vmp'); ?></label></th>
                            <td>
                                <input type="number" step="1" name="vmp_settings[finance][min_withdrawal]" class="regular-text" value="<?php echo esc_attr($finance['min_withdrawal'] ?? ''); ?>">
                                <p class="description"><?php _e('الحد الأدنى لرصيد البائع ليتمكن من طلب سحب.', 'vmp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('تفعيل الاشتراكات', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[finance][enable_subscriptions]" value="1" <?php checked(($finance['enable_subscriptions'] ?? '') === '1'); ?>>
                                    <?php _e('تفعيل نظام خطط الاشتراكات للبائعين', 'vmp'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- تبويب المظهر -->
                <!-- تبويب البريد الإلكتروني -->

                <div id="tab-email" class="vmp-tab-content" style="display:none;">

                    <h2><?php _e('إعدادات البريد الإلكتروني', 'vmp'); ?></h2>

                    

                    <table class="form-table">

                        <tr>

                            <th scope="row"><label><?php _e('مرسل البريد الإلكتروني', 'vmp'); ?></label></th>

                            <td>

                                <fieldset>

                                    <legend class="screen-reader-text"><?php _e('إعدادات المرسل', 'vmp'); ?></legend>

                                    <label>

                                        <span><?php _e('اسم المرسل', 'vmp'); ?></span>

                                        <input type="text" name="vmp_settings[email][from_name]" class="regular-text" value="<?php echo esc_attr($email['from_name'] ?? ''); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">

                                        <p class="description"><?php _e('اسم المرسل الذي سيظهر في رسائل البريد الإلكتروني. الافتراضي: اسم الموقع.', 'vmp'); ?></p>

                                    </label>

                                    <br><br>

                                    <label>

                                        <span><?php _e('بريد المرسل الإلكتروني', 'vmp'); ?></span>

                                        <input type="email" name="vmp_settings[email][from_email]" class="regular-text" value="<?php echo esc_attr($email['from_email'] ?? ''); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">

                                        <p class="description"><?php _e('البريد الإلكتروني للمرسل. الافتراضي: بريد المشرف.', 'vmp'); ?></p>

                                    </label>

                                </fieldset>

                            </td>

                        </tr>



                        <tr>

                            <th scope="row"><label><?php _e('إعدادات SMTP', 'vmp'); ?></label></th>

                            <td>

                                <fieldset>

                                    <legend class="screen-reader-text"><?php _e('إعدادات خادم SMTP', 'vmp'); ?></legend>

                                    <label>

                                        <input type="checkbox" name="vmp_settings[email][use_smtp]" value="1" <?php checked(($email['use_smtp'] ?? '') === '1'); ?>>

                                        <?php _e('استخدام SMTP مخصص', 'vmp'); ?>

                                    </label>

                                    <p class="description"><?php _e('إذا تم تفعيله، سيتم استخدام إعدادات SMTP أدناه بدلاً من دالة wp_mail() الافتراضية.', 'vmp'); ?></p>

                                    <br>

                                    <label>

                                        <span><?php _e('مضيف SMTP', 'vmp'); ?></span>

                                        <input type="text" name="vmp_settings[email][smtp_host]" class="regular-text" value="<?php echo esc_attr($email['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">

                                        <p class="description"><?php _e('عنوان خادم SMTP (مثال: smtp.gmail.com, smtp.mailgun.org).', 'vmp'); ?></p>

                                    </label>

                                    <br><br>

                                    <label>

                                        <span><?php _e('منفذ SMTP', 'vmp'); ?></span>

                                        <input type="number" name="vmp_settings[email][smtp_port]" class="small-text" value="<?php echo esc_attr($email['smtp_port'] ?? '587'); ?>" placeholder="587">

                                        <p class="description"><?php _e('منفذ خادم SMTP (عادة 587 لـ TLS، 465 لـ SSL، 25 غير مشفر).', 'vmp'); ?></p>

                                    </label>

                                    <br><br>

                                    <label>

                                        <span><?php _e('تشفير SMTP', 'vmp'); ?></span>

                                        <select name="vmp_settings[email][smtp_encryption]" class="regular-text">

                                            <option value="tls" <?php selected(($email['smtp_encryption'] ?? '') === 'tls'); ?>><?php _e('TLS (منفذ 587)', 'vmp'); ?></option>

                                            <option value="ssl" <?php selected(($email['smtp_encryption'] ?? '') === 'ssl'); ?>><?php _e('SSL (منفذ 465)', 'vmp'); ?></option>

                                            <option value="none" <?php selected(($email['smtp_encryption'] ?? '') === 'none'); ?>><?php _e('بدون تشفير (منفذ 25)', 'vmp'); ?></option>

                                        </select>

                                        <p class="description"><?php _e('نوع التشفير للاتصال بخادم SMTP.', 'vmp'); ?></p>

                                    </label>

                                    <br><br>

                                    <label>

                                        <span><?php _e('اسم مستخدم SMTP', 'vmp'); ?></span>

                                        <input type="text" name="vmp_settings[email][smtp_username]" class="regular-text" value="<?php echo esc_attr($email['smtp_username'] ?? ''); ?>">

                                        <p class="description"><?php _e('اسم المستخدم للمصادقة على خادم SMTP.', 'vmp'); ?></p>

                                    </label>

                                    <br><br>

                                    <label>

                                        <span><?php _e('كلمة مرور SMTP', 'vmp'); ?></span>

                                        <input type="password" name="vmp_settings[email][smtp_password]" class="regular-text" value="<?php echo esc_attr($email['smtp_password'] ?? ''); ?>" autocomplete="new-password">

                                        <p class="description"><?php _e('كلمة مرور المصادقة على خادم SMTP. اتركه فارغاً لعدم التغيير.', 'vmp'); ?></p>

                                    </label>

                                </fieldset>

                            </td>

                        </tr>



                        <tr>

                            <th scope="row"><label><?php _e('إشعارات المشرف', 'vmp'); ?></label></th>

                            <td>

                                <fieldset>

                                    <legend class="screen-reader-text"><?php _e('خيارات إشعارات المشرف', 'vmp'); ?></legend>

                                    <label>

                                        <input type="checkbox" name="vmp_settings[email][notify_new_vendor]" value="1" <?php checked(($email['notify_new_vendor'] ?? '') === '1'); ?>>

                                        <?php _e('إشعار عند تسجيل بائع جديد', 'vmp'); ?>

                                    </label>

                                    <p class="description"><?php _e('إرسال بريد إلكتروني للمشرف عند تقديم طلب تسجيل بائع جديد.', 'vmp'); ?></p>

                                    <br>

                                    <label>

                                        <input type="checkbox" name="vmp_settings[email][notify_new_order]" value="1" <?php checked(($email['notify_new_order'] ?? '') === '1'); ?>>

                                        <?php _e('إشعار عند طلب جديد', 'vmp'); ?>

                                    </label>

                                    <p class="description"><?php _e('إرسال بريد إلكتروني للمشرف عند استلام طلب جديد.', 'vmp'); ?></p>

                                    <br>

                                    <label>

                                        <input type="checkbox" name="vmp_settings[email][notify_withdrawal_request]" value="1" <?php checked(($email['notify_withdrawal_request'] ?? '') === '1'); ?>>

                                        <?php _e('إشعار عند طلب سحب', 'vmp'); ?>

                                    </label>

                                    <p class="description"><?php _e('إرسال بريد إلكتروني للمشرف عند طلب بائع سحب أموال.', 'vmp'); ?></p>

                                    <br>

                                    <label>

                                        <input type="checkbox" name="vmp_settings[email][notify_order_completed]" value="1" <?php checked(($email['notify_order_completed'] ?? '') === '1'); ?>>

                                        <?php _e('إشعار عند إكمال طلب', 'vmp'); ?>

                                    </label>

                                    <p class="description"><?php _e('إرسال بريد إلكتروني للمشرف عند تغيير حالة طلب إلى "مكتمل".', 'vmp'); ?></p>

                                </fieldset>

                            </td>

                        </tr>



                        <tr>

                            <th scope="row"><label><?php _e('إشعارات البائعين', 'vmp'); ?></label></th>

                            <td>

                                <fieldset>

                                    <legend class="screen-reader-text"><?php _e('خيارات إشعارات البائعين', 'vmp'); ?></legend>

                                    <label>

                                        <input type="checkbox" name="vmp_settings[email][notify_product_approved]" value="1" <?php checked(($email['notify_product_approved'] ?? '') === '1'); ?>>

                                        <?php _e('إشعار البائع عند قبول منتج', 'vmp'); ?>

                                    </label>

                                    <p class="description"><?php _e('إرسال بريد إلكتروني للبائع عند موافقة المشرف على منتجه.', 'vmp'); ?></p>

                                    <br>

                                    <label>

                                        <input type="checkbox" name="vmp_settings[email][notify_product_rejected]" value="1" <?php checked(($email['notify_product_rejected'] ?? '') === '1'); ?>>

                                        <?php _e('إشعار البائع عند رفض منتج', 'vmp'); ?>

                                    </label>

                                    <p class="description"><?php _e('إرسال بريد إلكتروني للبائع عند رفض المشرف لمنتجه.', 'vmp'); ?></p>

                                </fieldset>

                            </td>

                        </tr>



                        <tr>

                            <th scope="row"><label><?php _e('اختبار الإعدادات', 'vmp'); ?></label></th>

                            <td>

                                <button type="button" id="vmp-test-email" class="button button-secondary" data-nonce="<?php echo wp_create_nonce('vmp_test_email_nonce'); ?>">

                                    <?php _e('إرسال بريد اختبار', 'vmp'); ?>

                                </button>

                                <span id="vmp-test-email-result" class="description" style="margin-left: 10px; display: none;"></span>

                                <p class="description"><?php _e('يرسل بريد إلكتروني تجريبي إلى بريد المشرف للتحقق من إعدادات SMTP.', 'vmp'); ?></p>

                            </td>

                        </tr>

                    </table>

                </div>

                <div id="tab-display" class="vmp-tab-content" style="display:none;">
                    <h2><?php _e('إعدادات الصفحات', 'vmp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('صفحة تسجيل البائع', 'vmp'); ?></label></th>
                            <td>
                                <?php wp_dropdown_pages([
                                    'name' => 'vmp_settings[display][register_page]',
                                    'selected' => $display['register_page'] ?? '',
                                    'show_option_none' => __('— اختر صفحة —', 'vmp'),
                                    'option_none_value' => '',
                                ]); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('لوحة تحكم البائع', 'vmp'); ?></label></th>
                            <td>
                                <?php wp_dropdown_pages([
                                    'name' => 'vmp_settings[display][dashboard_page]',
                                    'selected' => $display['dashboard_page'] ?? '',
                                    'show_option_none' => __('— اختر صفحة —', 'vmp'),
                                    'option_none_value' => '',
                                ]); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('صفحة الأحكام والشروط', 'vmp'); ?></label></th>
                            <td>
                                <?php wp_dropdown_pages([
                                    'name' => 'vmp_settings[display][terms_page]',
                                    'selected' => $display['terms_page'] ?? '',
                                    'show_option_none' => __('— اختر صفحة —', 'vmp'),
                                    'option_none_value' => '',
                                ]); ?>
                                <p class="description"><?php _e('اختر صفحة تعرض الأحكام والشروط التي يجب أن يوافق عليها المتقدمون.', 'vmp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- تبويب تسجيل البائعين -->
                <div id="tab-registration" class="vmp-tab-content" style="display:none;">
                    <h2><?php _e('إعدادات تسجيل البائعين متعدد الخطوات', 'vmp'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('تفعيل تسجيل البائعين', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[general][enable_registration]" value="1" <?php checked(($general['enable_registration'] ?? '') === '1'); ?>>
                                    <?php _e('السماح للبائعين الجدد بالتسجيل', 'vmp'); ?>
                                </label>
                            </td>
                        </tr>

                        <!-- إعدادات الخطوة 1: بيانات الحساب -->
                        <tr>
                            <th scope="row"><label><?php _e('الخطوة 1: بيانات الحساب', 'vmp'); ?></label></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('خيارات خطوة الحساب', 'vmp'); ?></legend>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][require_email_verification]" value="1" <?php checked(($settings['registration']['require_email_verification'] ?? '') === '1'); ?>>
                                        <?php _e('طلب التحقق من البريد الإلكتروني', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('إذا تم تفعيله، يجب على المستخدم التحقق من بريده الإلكتروني قبل إكمال التسجيل.', 'vmp'); ?></p>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][auto_login_after_register]" value="1" <?php checked(($settings['registration']['auto_login_after_register'] ?? '') === '1'); ?>>
                                        <?php _e('تسجيل الدخول تلقائياً بعد التسجيل', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('إذا تم تفعيله، سيتم تسجيل دخول البائع تلقائياً بعد إنشاء الحساب.', 'vmp'); ?></p>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][require_strong_password]" value="1" <?php checked(($settings['registration']['require_strong_password'] ?? '') === '1'); ?>>
                                        <?php _e('طلب كلمة مرور قوية', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('يتطلب 8 أحرف على الأقل، حرف كبير، رقم، ورمز خاص.', 'vmp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- إعدادات الخطوة 2: بيانات المتجر -->
                        <tr>
                            <th scope="row"><label><?php _e('الخطوة 2: بيانات المتجر', 'vmp'); ?></label></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('خيارات خطوة المتجر', 'vmp'); ?></legend>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][require_store_address]" value="1" <?php checked(($settings['registration']['require_store_address'] ?? '') === '1'); ?>>
                                        <?php _e('طلب العنوان التفصيلي للمتجر', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('إذا تم تفعيله، سيكون حقل العنوان التفصيلي مطلوباً.', 'vmp'); ?></p>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][require_store_description]" value="1" <?php checked(($settings['registration']['require_store_description'] ?? '') === '1'); ?>>
                                        <?php _e('طلب وصف المتجر', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('إذا تم تفعيله، سيكون حقل وصف المتجر مطلوباً.', 'vmp'); ?></p>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][allow_custom_domain]" value="1" <?php checked(($settings['registration']['allow_custom_domain'] ?? '') === '1'); ?>>
                                        <?php _e('السماح بنطاق مخصص للمتجر', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('إذا تم تفعيله، يمكن للبائع ربط نطاق مخصص بمتجره (ميزة خطة الاشتراك).', 'vmp'); ?></p>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][require_whatsapp]" value="1" <?php checked(($settings['registration']['require_whatsapp'] ?? '') === '1'); ?>>
                                        <?php _e('طلب رقم واتساب للتواصل', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('إذا تم تفعيله، سيكون حقل رقم واتساب مطلوباً.', 'vmp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- إعدادات الخطوة 3: خطة الاشتراك -->
                        <tr>
                            <th scope="row"><label><?php _e('الخطوة 3: خطة الاشتراك', 'vmp'); ?></label></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('خيارات خطوة الخطة', 'vmp'); ?></legend>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][require_plan_selection]" value="1" <?php checked(($settings['registration']['require_plan_selection'] ?? '') === '1'); ?>>
                                        <?php _e('طلب اختيار خطة الاشتراك', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('إذا تم تفعيله، يجب على البائع اختيار خطة اشتراك.', 'vmp'); ?></p>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][show_plan_features]" value="1" <?php checked(($settings['registration']['show_plan_features'] ?? '') === '1'); ?>>
                                        <?php _e('عرض مميزات الخطط', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('عرض مقارنة مميزات الخطط للاختيار بينها.', 'vmp'); ?></p>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][default_free_plan]" value="1" <?php checked(($settings['registration']['default_free_plan'] ?? '') === '1'); ?>>
                                        <?php _e('خطة مجانية افتراضية', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('تعيين خطة مجانية كخيار افتراضي.', 'vmp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- إعدادات تدفق التسجيل -->
                        <tr>
                            <th scope="row"><label><?php _e('تدفق التسجيل', 'vmp'); ?></label></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('خيارات تدفق التسجيل', 'vmp'); ?></legend>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][save_progress]" value="1" <?php checked(($settings['registration']['save_progress'] ?? '') === '1'); ?>>
                                        <?php _e('حفظ التقدم تلقائياً', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('يحفظ بيانات كل خطوة عند الانتقال للخطوة التالية.', 'vmp'); ?></p>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][show_progress_bar]" value="1" <?php checked(($settings['registration']['show_progress_bar'] ?? '') === '1'); ?>>
                                        <?php _e('عرض شريط التقدم', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('يعرض شريط تقدم يوضح الخطوات المكتملة.', 'vmp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- إعدادات الموافقة والشروط -->
                        <tr>
                            <th scope="row"><label><?php _e('الموافقة والشروط', 'vmp'); ?></label></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('خيارات الموافقة', 'vmp'); ?></legend>
                                    <label>
                                        <input type="checkbox" name="vmp_settings[registration][terms_required]" value="1" <?php checked(($settings['registration']['terms_required'] ?? '') === '1'); ?>>
                                        <?php _e('طلب الموافقة على الأحكام والشروط', 'vmp'); ?>
                                    </label>
                                    <p class="description"><?php _e('إذا تم تفعيله، يجب على البائع الموافقة على الأحكام والشروط.', 'vmp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- رسائل النظام -->
                        <tr>
                            <th scope="row"><label><?php _e('رسالة الترحيب بالبريد الإلكتروني', 'vmp'); ?></label></th>
                            <td>
                                <textarea name="vmp_settings[messages][welcome_email]" rows="4" class="large-text"><?php echo esc_textarea($messages['welcome_email'] ?? ''); ?></textarea>
                                <p class="description"><?php _e('بريد إلكتروني ترحيبي يُرسل للبائع بعد التسجيل (يمكن استخدام HTML).', 'vmp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('رسالة انتظار المراجعة', 'vmp'); ?></label></th>
                            <td>
                                <textarea name="vmp_settings[messages][pending_review]" rows="4" class="large-text"><?php echo esc_textarea($messages['pending_review'] ?? ''); ?></textarea>
                                <p class="description"><?php _e('الرسالة التي تظهر للبائع عند انتظار مراجعة حسابه.', 'vmp'); ?></p>
                            </td>
                        </tr>

                        <!-- إعدادات متقدمة -->
                        <tr>
                            <th scope="row"><label><?php _e('إعدادات متقدمة', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[registration][enable_session_resume]" value="1" <?php checked(($settings['registration']['enable_session_resume'] ?? '') === '1'); ?>>
                                    <?php _e('السماح باستئناف التسجيل', 'vmp'); ?>
                                </label>
                                <p class="description"><?php _e('إذا تم تفعيله، يمكن للمستخدمين استكمال التسجيل من حيث توقفوا (تُحفظ البيانات في جلسة المتصفح).', 'vmp'); ?></p>
                                <label>
                                    <input type="checkbox" name="vmp_settings[registration][log_registration_attempts]" value="1" <?php checked(($settings['registration']['log_registration_attempts'] ?? '') === '1'); ?>>
                                    <?php _e('تسجيل محاولات التسجيل', 'vmp'); ?>
                                </label>
                                <p class="description"><?php _e('يسجل جميع محاولات التسجيل (ناجحة وفاشلة) في سجلات الأخطاء.', 'vmp'); ?></p>
                                <label>
                                    <input type="checkbox" name="vmp_settings[registration][send_admin_notification]" value="1" <?php checked(($settings['registration']['send_admin_notification'] ?? '') === '1'); ?>>
                                    <?php _e('إرسال إشعار للمشرف عند تسجيل بائع جديد', 'vmp'); ?>
                                </label>
                                <p class="description"><?php _e('يرسل بريد إلكتروني للمشرف عند تسجيل بائع جديد.', 'vmp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- زر الحفظ -->
                <p class="submit">
                    <button type="submit" class="button button-primary button-large" id="vmp-save-settings-btn">
                        <?php _e('حفظ الإعدادات', 'vmp'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // ── التبديل بين التبويبات ──
    $(document).on('click', '.vmp-admin-tab', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.vmp-admin-tab').removeClass('active');
        $(this).addClass('active');
        $('.vmp-tab-content').hide();
        var $target = $('#tab-' + tab);
        if ($target.length) {
            $target.show();
        }
    });

    // ── حفظ الإعدادات ──
    $('#vmp-settings-form').on('submit', function(e) {
        if ($(this).data('ajax') !== 1) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        var $form = $(this);
        var $btn = $('#vmp-save-settings-btn');
        var $notice = $('#vmp-settings-notice');

        // تعطيل الزر وعرض رسالة التحميل
        $btn.prop('disabled', true).text('<?php _e('جاري الحفظ...', 'vmp'); ?>');
        $notice.hide().removeClass('notice-success notice-error');

        // جمع بيانات النموذج وتحويلها إلى بنية متداخلة
        var formData = $form.serializeArray();
        var data = {};

        $.each(formData, function(i, field) {
            var name = field.name;
            var value = field.value;

            if (name.startsWith('vmp_settings[')) {
                var parts = name.replace(/\]/g, '').split('[');
                if (parts.length >= 3) {
                    var section = parts[1];
                    var key = parts[2];
                    if (!data[section]) data[section] = {};
                    data[section][key] = value;
                }
            } else if (name === 'nonce') {
                data.nonce = value;
            } else if (name === 'action') {
                data.action = value;
            }
        });

        // ضمان إرسال checkbox غير المحددة بقيمة 0
        if (!data.general) data.general = {};
        if (!data.finance) data.finance = {};
        if (!data.display) data.display = {};
        if (!data.registration) data.registration = {};
        if (!data.messages) data.messages = {};
        if (!data.email) data.email = {};

        // التأكد من وجود جميع الحقول (حتى الغير محددة)
        ['enable_registration', 'auto_approve_vendors', 'auto_approve_products'].forEach(function(key) {
            if (data.general[key] === undefined) data.general[key] = '0';
        });
        if (data.finance.enable_subscriptions === undefined) data.finance.enable_subscriptions = '0';

        // حقول التسجيل
        ['require_email_verification', 'auto_login_after_register', 'require_strong_password',
         'require_store_address', 'require_store_description', 'allow_custom_domain', 'require_whatsapp',
         'require_plan_selection', 'show_plan_features', 'default_free_plan',
         'save_progress', 'show_progress_bar', 'terms_required', 'send_welcome_email',
         'enable_session_resume', 'log_registration_attempts', 'send_admin_notification'
        ].forEach(function(key) {
            if (data.registration[key] === undefined) data.registration[key] = '0';
        });

        // رسائل النظام

        // إعدادات البريد الإلكتروني
        ['use_smtp', 'from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'notify_new_vendor', 'notify_new_order', 'notify_withdrawal_request', 'notify_order_completed', 'notify_product_approved', 'notify_product_rejected'].forEach(function(key) {
            if (data.email[key] === undefined) {
                if (['notify_new_vendor', 'notify_new_order', 'notify_withdrawal_request', 'notify_order_completed', 'notify_product_approved', 'notify_product_rejected', 'use_smtp'].includes(key)) {
                    data.email[key] = '0';
                } else {
                    data.email[key] = '';
                }
            }
        });
        ['register_success', 'register_pending', 'register_approved', 'register_rejected', 'welcome_email', 'pending_review'].forEach(function(key) {
            if (data.messages[key] === undefined) {
                data.messages[key] = $('textarea[name="vmp_settings[messages][' + key + ']"]').val() || '';
            }
        });

        // إعدادات العرض
        ['dashboard_page', 'register_page', 'store_page', 'terms_page'].forEach(function(key) {
            if (data.display[key] === undefined) data.display[key] = '';
        });

        // إرسال الطلب
        $.post(ajaxurl, {
            action: 'vmp_admin_save_settings',
            nonce: data.nonce,
            vmp_settings: {
                email: data.email,
                general: data.general,
                finance: data.finance,
                display: data.display,
                registration: data.registration,
                messages: data.messages
            }
        }, function(response) {
            $btn.prop('disabled', false).text('<?php _e('حفظ الإعدادات', 'vmp'); ?>');

            if (response.success) {
                $notice.show().addClass('notice-success').html('<p>' + response.data.message + '</p>');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $notice.show().addClass('notice-error').html('<p>' + response.data.message + '</p>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('<?php _e('حفظ الإعدادات', 'vmp'); ?>');
            $notice.show().addClass('notice-error').html('<p><?php _e('حدث خطأ في الاتصال بالخادم.', 'vmp'); ?></p>');
        });
    });
});

jQuery(function($) {
    $(document).on("click", "#vmp-test-email", function() {
        var $btn = $(this);
        var $result = $("#vmp-test-email-result");
        var nonce = $btn.data("nonce");

        $btn.prop("disabled", true).text("<?php _e('جاري الإرسال...', 'vmp'); ?>");
        $result.hide().removeClass("notice-success notice-error");

        $.post(ajaxurl, {
            action: "vmp_test_email",
            nonce: nonce
        }, function(response) {
            $btn.prop("disabled", false).text("<?php _e('إرسال بريد اختبار', 'vmp'); ?>");
            $result.show();
            if (response.success) {
                $result.addClass("notice-success").text(response.data.message);
            } else {
                $result.addClass("notice-error").text(response.data.message);
            }
        }).fail(function() {
            $btn.prop("disabled", false).text("<?php _e('إرسال بريد اختبار', 'vmp'); ?>");
            $result.show().addClass("notice-error").text("<?php _e('حدث خطأ في الاتصال بالخادم.', 'vmp'); ?>");
        });
    });
});


</script>

<style>
.vmp-admin-wrap .vmp-admin-tabs {
    display: flex;
    gap: 4px;
    margin: 20px 0 0;
    background: #f1f5f9;
    padding: 8px 8px 0;
    border-radius: 8px 8px 0 0;
}
.vmp-admin-wrap .vmp-admin-tab {
    padding: 10px 20px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: #475569;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}
.vmp-admin-wrap .vmp-admin-tab:hover {
    color: #0f172a;
    background: #e2e8f0;
}
.vmp-admin-wrap .vmp-admin-tab.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
    background: #fff;
}
.vmp-admin-wrap .vmp-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.vmp-admin-wrap .vmp-card-body {
    padding: 24px;
}
.vmp-admin-wrap .form-table th {
    width: 250px;
    padding-top: 20px;
}
.vmp-admin-wrap .form-table td {
    padding-top: 20px;
}
.vmp-admin-wrap fieldset {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin: 0;
}
.vmp-admin-wrap fieldset label {
    display: block;
    margin: 12px 0;
    cursor: pointer;
}
.vmp-admin-wrap fieldset label:first-child {
    margin-top: 0;
}
.vmp-admin-wrap fieldset p.description {
    margin: 4px 0 0 24px;
    color: #64748b;
    font-size: 13px;
}
.vmp-admin-wrap .submit {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}
</style>
