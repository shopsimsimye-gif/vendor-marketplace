<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * القالب الرئيسي لتسجيل البائعين - نموذج الخطوة الواحدة (تسجيل جديد / ترقية)
 *
 * @package VMP\Templates
 * @version 3.0.0
 */

$container = \VMP\Core\Container::getInstance();
$vendor_repo = $container->make(\VMP\Contracts\VendorRepositoryInterface::class);
$request_repo = $container->make(\VMP\Contracts\VendorRequestRepositoryInterface::class);

$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;
$current_user_id = $is_logged_in ? $current_user->ID : 0;

$vendor = null;
$pending_request = null;

if ($current_user_id) {
    $vendor = $vendor_repo->findByUserId($current_user_id);
    if ($vendor) {
        $settings = get_option('vmp_settings', []);
        $dashboard_page_id = !empty($settings['display']['dashboard_page']) ? (int) $settings['display']['dashboard_page'] : 0;
        $redirect_url = $dashboard_page_id && get_post($dashboard_page_id) ? get_permalink($dashboard_page_id) : home_url('/vendor-dashboard/');
        ?>
        <script>
            window.location.href = '<?php echo esc_js($redirect_url); ?>';
        </script>
        <div class="vmp-wrap vmp-register-wrap">
            <div class="vmp-container" style="max-width: 600px; text-align: center; margin: 40px auto;">
                <div class="vmp-card" style="padding: 32px;">
                    <div class="vmp-notice vmp-notice-info">
                        <span class="dashicons dashicons-admin-users" style="font-size: 40px; width:40px; height:40px; margin-bottom:12px;"></span>
                        <h3><?php esc_html_e('أنت مسجل كبائع بالفعل!', 'vmp'); ?></h3>
                        <p><?php esc_html_e('جاري تحويلك إلى لوحة التحكم الخاصة بمتجرك...', 'vmp'); ?></p>
                        <a href="<?php echo esc_url($redirect_url); ?>" class="vmp-btn vmp-btn-primary" style="margin-top:16px;">
                            <?php esc_html_e('الذهاب للوحة التحكم', 'vmp'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return;
    }

    // التحقق من وجود طلب قيد المراجعة للمستخدم
    if (method_exists($request_repo, 'findByUserId')) {
        $pending_request = $request_repo->findByUserId($current_user_id);
    }
}

$settings = get_option('vmp_settings', []);
$terms_page_id = !empty($settings['registration']['terms_page_url']) ? (int) $settings['registration']['terms_page_url'] : 
                 (!empty($settings['display']['terms_page']) ? (int) $settings['display']['terms_page'] : 0);
$terms_url = $terms_page_id && get_post($terms_page_id) ? get_permalink($terms_page_id) : home_url('/terms/');
?>

<div class="vmp-wrap vmp-register-wrap">
    <div class="vmp-container" style="max-width: 700px; margin: 0 auto; padding: 20px 15px;">
        
        <header class="vmp-header-bar" style="text-align: center; margin-bottom: 28px;">
            <h1 style="font-size: 28px; margin-bottom: 8px; color: var(--vmp-text, #1e293b);">
                <?php echo $is_logged_in ? esc_html__('ترقية الحساب إلى بائع', 'vmp') : esc_html__('نموذج الانضمام كبائع', 'vmp'); ?>
            </h1>
            <p style="font-size: 15px; color: var(--vmp-text-muted, #64748b);">
                <?php echo $is_logged_in 
                    ? esc_html__('قدم طلب ترقية حسابك الحالي للبدء بالبيع على منصتنا.', 'vmp')
                    : esc_html__('أنشئ حسابك الجديد وقدم طلب الانضمام كبائع في خطوة واحدة.', 'vmp'); ?>
            </p>
        </header>

        <?php if ($pending_request && in_array($pending_request->status, ['submitted', 'pending'], true)) : ?>
            <!-- عرض حالة الطلب في حال وجود طلب سابق قيد المراجعة -->
            <div class="vmp-card vmp-status-card" style="text-align: center; padding: 40px 24px; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <div class="vmp-status-icon" style="margin-bottom: 16px;">
                    <span class="dashicons dashicons-clock" style="font-size: 56px; width: 56px; height: 56px; color: #f59e0b;"></span>
                </div>
                <h2 style="font-size: 22px; margin-bottom: 12px; color: #1e293b;"><?php esc_html_e('طلبك للانضمام كبائع قيد المراجعة', 'vmp'); ?></h2>
                <p style="font-size: 15px; color: #64748b; line-height: 1.6; max-width: 500px; margin: 0 auto 20px;">
                    <?php esc_html_e('لقد قمت بتقديم طلب انضمام سابقاً، وهو الآن قيد المراجعة من قبل فريق العمل. سنقوم بإرسال إشعار لك عبر البريد الإلكتروني فور الموافقة عليه.', 'vmp'); ?>
                </p>
                <div class="vmp-status-badge" style="display: inline-block; padding: 6px 16px; background: #fef3c7; color: #92400e; border-radius: 20px; font-weight: 600; font-size: 13px;">
                    <?php esc_html_e('حالة الطلب: قيد المراجعة', 'vmp'); ?>
                </div>
            </div>
        <?php else : ?>

            <div class="vmp-card vmp-form-card" style="background: #ffffff; border-radius: 14px; padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                
                <form id="vmp-single-register-form" class="vmp-single-step-form" method="POST" enctype="multipart/form-data" novalidate>
                    <?php 
                    if ($is_logged_in) {
                        wp_nonce_field('vmp_register_apply', 'vmp_register_apply_nonce');
                    } else {
                        wp_nonce_field('vmp_register_guest', 'vmp_register_guest_nonce');
                    }
                    ?>
                    <input type="hidden" name="is_logged_in" value="<?php echo $is_logged_in ? '1' : '0'; ?>">

                    <?php if (!$is_logged_in) : ?>
                        <!-- ── نموذج البائع (تسجيل جديد) ── -->
                        <div class="vmp-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
                            <div class="vmp-form-group">
                                <label for="vmp_first_name" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('الاسم الأول', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                                </label>
                                <input type="text" id="vmp_first_name" name="first_name" class="vmp-input" required autocomplete="given-name" placeholder="أدخل الاسم الأول" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>

                            <div class="vmp-form-group">
                                <label for="vmp_last_name" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('الاسم الأخير', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                                </label>
                                <input type="text" id="vmp_last_name" name="last_name" class="vmp-input" required autocomplete="family-name" placeholder="أدخل الاسم الأخير" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>
                        </div>

                        <div class="vmp-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
                            <div class="vmp-form-group">
                                <label for="vmp_username" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('اسم المستخدم', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                                </label>
                                <input type="text" id="vmp_username" name="username" class="vmp-input" required autocomplete="username" placeholder="مثال: username123" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; direction: ltr;">
                            </div>

                            <div class="vmp-form-group">
                                <label for="vmp_country" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('الدولة', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                                </label>
                                <input type="text" id="vmp_country" name="country" class="vmp-input" required placeholder="مثال: المملكة العربية السعودية" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>
                        </div>

                        <div class="vmp-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
                            <div class="vmp-form-group">
                                <label for="vmp_phone" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('رقم الموبايل', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                                </label>
                                <input type="tel" id="vmp_phone" name="phone" class="vmp-input" required dir="ltr" placeholder="+966500000000" autocomplete="tel" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>

                            <div class="vmp-form-group">
                                <label for="vmp_email" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('البريد الإلكتروني', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                                </label>
                                <input type="email" id="vmp_email" name="email" class="vmp-input" required autocomplete="email" placeholder="name@domain.com" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; direction: ltr;">
                            </div>
                        </div>

                        <div class="vmp-form-group" style="margin-bottom: 16px;">
                            <label for="vmp_password" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                <?php esc_html_e('كلمة المرور', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                            </label>
                            <div class="vmp-password-wrapper" style="position: relative;">
                                <input type="password" id="vmp_password" name="password" class="vmp-input" required minlength="8" autocomplete="new-password" placeholder="8 أحرف على الأقل" style="width: 100%; padding: 10px 40px 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                <button type="button" class="vmp-toggle-password" aria-label="<?php esc_attr_e('إظهار/إخفاء كلمة المرور', 'vmp'); ?>" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b;">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                        </div>

                    <?php else : ?>
                        <!-- ── نموذج التسجيل ترقية (لمستخدم مسجل بالفعل) ── -->
                        <div class="vmp-notice vmp-notice-info" style="margin-bottom: 20px; padding: 12px 16px; background: #eff6ff; border-right: 4px solid #3b82f6; border-radius: 6px; font-size: 14px; color: #1e40af;">
                            <p style="margin: 0;">
                                <?php printf(esc_html__('مرحباً %s، يمكنك إرسال طلب ترقية حسابك إلى بائع أدناه.', 'vmp'), '<strong>' . esc_html($current_user->display_name) . '</strong>'); ?>
                            </p>
                        </div>

                        <div class="vmp-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
                            <div class="vmp-form-group">
                                <label for="vmp_first_name" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('الاسم الأول (اختياري للتعديل)', 'vmp'); ?>
                                </label>
                                <input type="text" id="vmp_first_name" name="first_name" class="vmp-input" value="<?php echo esc_attr($current_user->first_name); ?>" placeholder="الاسم الأول" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>

                            <div class="vmp-form-group">
                                <label for="vmp_last_name" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('الاسم الأخير (اختياري للتعديل)', 'vmp'); ?>
                                </label>
                                <input type="text" id="vmp_last_name" name="last_name" class="vmp-input" value="<?php echo esc_attr($current_user->last_name); ?>" placeholder="الاسم الأخير" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>
                        </div>

                        <?php 
                        $existing_phone = get_user_meta($current_user_id, 'billing_phone', true) ?: get_user_meta($current_user_id, 'phone', true);
                        $existing_country = get_user_meta($current_user_id, 'billing_country', true) ?: get_user_meta($current_user_id, 'country', true);
                        ?>

                        <div class="vmp-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
                            <div class="vmp-form-group">
                                <label for="vmp_country" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('الدولة', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                                </label>
                                <input type="text" id="vmp_country" name="country" class="vmp-input" required value="<?php echo esc_attr($existing_country); ?>" placeholder="مثال: المملكة العربية السعودية" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>

                            <div class="vmp-form-group">
                                <label for="vmp_phone" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                                    <?php esc_html_e('رقم الموبايل', 'vmp'); ?> <span class="required" style="color:#ef4444;">*</span>
                                </label>
                                <input type="tel" id="vmp_phone" name="phone" class="vmp-input" required value="<?php echo esc_attr($existing_phone); ?>" dir="ltr" placeholder="+966500000000" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ── حقل وثيقة أو ترخيص النشاط التجاري (اختياري) ── -->
                    <div class="vmp-form-group" style="margin-bottom: 20px;">
                        <label for="vmp_license_document" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">
                            <?php esc_html_e('وثيقة أو ترخيص النشاط التجاري (اختياري)', 'vmp'); ?>
                        </label>
                        <input type="file" id="vmp_license_document" name="license_document" accept="application/pdf,image/*" style="width: 100%; padding: 8px 12px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; font-size: 13px;">
                        <span class="vmp-input-hint" style="display: block; font-size: 12px; color: #64748b; margin-top: 4px;">
                            <?php esc_html_e('الصيغ المسموحة: PDF, JPG, PNG (حد أقصى 5 ميجابايت).', 'vmp'); ?>
                        </span>
                    </div>

                    <!-- ── الموافقة على الشروط والأحكام ── -->
                    <div class="vmp-form-group vmp-terms-group" style="margin-bottom: 24px;">
                        <label class="vmp-checkbox-label" style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 14px; color: #334155;">
                            <input type="checkbox" name="accept_terms" value="1" required style="width: 18px; height: 18px; accent-color: #2563eb; margin-top: 2px;">
                            <span>
                                <?php printf(esc_html__('أوافق على %sالشروط والأحكام%s وسياسة المنصة', 'vmp'), '<a href="' . esc_url($terms_url) . '" target="_blank" rel="noopener" style="color: #2563eb; text-decoration: underline;">', '</a>'); ?>
                                <span class="required" style="color:#ef4444;">*</span>
                            </span>
                        </label>
                    </div>

                    <!-- ── زر الإرسال ── -->
                    <div class="vmp-form-actions">
                        <button type="submit" class="vmp-btn vmp-btn-primary vmp-btn-submit" id="vmp_submit_btn" style="width: 100%; padding: 14px; background: #2563eb; color: #ffffff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s ease;">
                            <span class="vmp-btn-text">
                                <?php echo $is_logged_in ? esc_html__('إرسال طلب الترقية', 'vmp') : esc_html__('تسجيل كبائع', 'vmp'); ?>
                            </span>
                            <span class="vmp-btn-loading" style="display:none;">
                                <span class="dashicons dashicons-update spin" style="animation: spin 1s linear infinite; margin-left: 6px;"></span>
                                <?php esc_html_e('جاري إرسال الطلب...', 'vmp'); ?>
                            </span>
                        </button>
                    </div>
                </form>

            </div>

        <?php endif; ?>

        <!-- ── رسالة نجاح (تظهر بعد الإرسال) ── -->
        <div id="vmp-success-message" class="vmp-card vmp-success-message" style="display: none; text-align: center; padding: 48px 24px; background: #ffffff; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-top: 20px;">
            <div class="vmp-success-icon" style="margin-bottom: 20px;">
                <span class="dashicons dashicons-yes-alt" style="font-size: 64px; width: 64px; height: 64px; color: #10b981;"></span>
            </div>
            <h2 style="font-size: 24px; color: #0f172a; margin-bottom: 12px;"><?php esc_html_e('انضم إلينا كبائع قيد المراجعة', 'vmp'); ?></h2>
            <p id="vmp_success_text" style="font-size: 16px; color: #475569; max-width: 520px; margin: 0 auto 24px; line-height: 1.6;">
                <?php esc_html_e('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة من قبل المشرف. سنقوم بإرسال رسالة تفيد بالموافقة على بريدك الإلكتروني قريباً.', 'vmp'); ?>
            </p>
            <div id="vmp_success_actions">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="vmp-btn vmp-btn-outline" style="display: inline-block; padding: 10px 24px; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; font-size: 14px; font-weight: 500;">
                    <?php esc_html_e('الرئيسية', 'vmp'); ?>
                </a>
            </div>
        </div>

        <!-- ── رسالة خطأ (تظهر عند الفشل) ── -->
        <div id="vmp-error-message" class="vmp-card vmp-error-message" style="display: none; text-align: center; padding: 32px 24px; background: #ffffff; border-radius: 14px; border: 1px solid #fca5a5; margin-top: 20px;">
            <div class="vmp-error-icon" style="margin-bottom: 12px;">
                <span class="dashicons dashicons-dismiss" style="font-size: 48px; width: 48px; height: 48px; color: #ef4444;"></span>
            </div>
            <h3 style="font-size: 18px; color: #991b1b; margin-bottom: 8px;"><?php esc_html_e('حدث خطأ أثناء إرسال الطلب', 'vmp'); ?></h3>
            <p id="vmp_error_text" style="font-size: 14px; color: #b91c1c; margin-bottom: 16px;"></p>
            <button type="button" class="vmp-btn vmp-btn-primary" id="vmp_retry_btn" style="padding: 8px 20px; background: #ef4444; color: #fff; border: none; border-radius: 6px; cursor: pointer;">
                <?php esc_html_e('إعادة المحاولة', 'vmp'); ?>
            </button>
        </div>

    </div>
</div>


