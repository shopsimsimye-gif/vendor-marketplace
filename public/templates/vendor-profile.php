<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من البائع ──
$user_id = get_current_user_id();
if (!$user_id) {
    echo '<div class="vmp-notice vmp-notice-error">' . esc_html__('يجب تسجيل الدخول أولاً.', 'vmp') . '</div>';
    return;
}

$vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
$vendor = $vendor_repo->findByUserId($user_id);

if (!$vendor) {
    echo '<div class="vmp-notice vmp-notice-error">' . esc_html__('البائع غير موجود.', 'vmp') . '</div>';
    return;
}
if ($vendor->status !== 'approved') {
    echo '<div class="vmp-notice vmp-notice-warning">' . esc_html__('حسابك قيد المراجعة أو غير معتمد.', 'vmp') . '</div>';
    return;
}

// ── جلب خطة الاشتراك والميزات ──
$sub_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
$plan_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);
$active_sub = $sub_repo->findActiveByVendor((int) $vendor->id);
$plan = $active_sub ? $plan_repo->find((int) $active_sub->plan_id) : null;
$features = $plan ? $plan_repo->getFeatures((int) $plan->id) : [];

$has_social   = !empty($features['social_links']);
$has_video    = !empty($features['product_video']);
$has_address  = !empty($features['store_address']);

// ── استخدام wp_get_attachment_image_url بدلاً من wp_get_attachment_url ──
$logo_url   = ($vendor->store_logo && wp_attachment_is_image($vendor->store_logo)) 
    ? wp_get_attachment_image_url($vendor->store_logo, 'medium') 
    : '';
$banner_url = ($vendor->store_banner && wp_attachment_is_image($vendor->store_banner)) 
    ? wp_get_attachment_image_url($vendor->store_banner, 'large') 
    : '';

$user = wp_get_current_user();

// ── التأكد من تحميل wp.media ──
if (!did_action('wp_enqueue_media')) {
    wp_enqueue_media();
}

// ── بناء رابط المتجر ──
$store_base = get_option('vmp_store_base', 'store');
$store_slug = !empty($vendor->store_slug) ? $vendor->store_slug : sanitize_title($vendor->store_name);
if (empty($store_slug)) {
    $store_slug = 'store-' . $vendor->id;
}
$store_url = home_url('/' . $store_base . '/' . $store_slug . '/');

// ── nonce موحد للصفحة ──
$public_nonce = wp_create_nonce('vmp_public_nonce');
?>

<div class="vmp-wrap">
    <!-- التنقل -->
    <?php
    $nav_file = VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php';
    if (file_exists($nav_file)) {
        require_once $nav_file;
    }
    ?>

    <div class="vmp-card" style="max-width: 820px; margin: 0 auto;">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php echo esc_html__('إعدادات المتجر', 'vmp'); ?></h2>
            <span class="vmp-badge-status vmp-status-approved"><?php echo esc_html__('نشط', 'vmp'); ?></span>
        </div>

        <!-- ── Toast Notification Container ── -->
        <div id="vmp-toast-container" style="position: fixed; top: 24px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;"></div>

        <!-- ── عرض رابط المتجر الحالي ── -->
        <div class="vmp-store-url-box" style="background: #f8fafc; border: 2px solid #2563eb; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
            <div class="vmp-store-url-label" style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 16px; color: #1e293b;">
                <span class="dashicons dashicons-admin-links" style="font-size: 24px; width: 24px; height: 24px;"></span>
                <?php echo esc_html__('رابط متجرك:', 'vmp'); ?>
            </div>
            <div class="vmp-store-url-display" style="flex: 1; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <input type="text" id="vmp-store-url-input" value="<?php echo esc_url($store_url); ?>" readonly 
                       style="flex: 1; min-width: 200px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: #ffffff; color: #1e293b; direction: ltr; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
                <button class="vmp-btn vmp-btn-sm vmp-copy-url-btn" data-url="<?php echo esc_url($store_url); ?>" 
                        style="display: inline-flex; align-items: center; gap: 4px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; background: #2563eb; color: #ffffff; transition: 0.2s;">
                    <span class="dashicons dashicons-clipboard" style="font-size: 18px; width: 18px; height: 18px;"></span> <?php echo esc_html__('نسخ', 'vmp'); ?>
                </button>
            </div>
            <div class="vmp-store-url-hint" style="width: 100%; font-size: 13px; color: #64748b; margin-top: 4px;">
                <?php echo esc_html__('استخدم هذا الرابط لمشاركة متجرك مع العملاء.', 'vmp'); ?>
            </div>
        </div>

        <form id="vmp-profile-form" class="vmp-profile-form" data-action="vmp_vendor_update_profile" enctype="multipart/form-data">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($public_nonce); ?>">

            <!-- قسم: معلومات المتجر الأساسية -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">🏪</span>
                <?php echo esc_html__('معلومات المتجر', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('اسم المتجر', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="store_name" class="vmp-input" value="<?php echo esc_attr($vendor->store_name ?? ''); ?>" required>
                </div>
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('رقم الهاتف', 'vmp'); ?></label>
                    <input type="tel" name="store_phone" class="vmp-input" value="<?php echo esc_attr($vendor->store_phone ?? ''); ?>" dir="ltr" placeholder="+966 500 000 000">
                </div>
            </div>

            <!-- ── رابط المتجر (Slug) ── -->
            <div class="vmp-form-group">
                <label><?php echo esc_html__('رابط المتجر (Slug)', 'vmp'); ?> <span class="required">*</span></label>
                <div style="display:flex; align-items:center; direction:ltr;">
                    <span style="background:var(--vmp-border); padding:11px 14px; border-radius:8px 0 0 8px; font-size:13px; color:var(--vmp-text-muted); border:1.5px solid var(--vmp-border); border-right:none;">
                        <?php echo esc_url(home_url('/' . $store_base . '/')); ?>
                    </span>
                    <input type="text" name="store_slug" class="vmp-input" value="<?php echo esc_attr($vendor->store_slug ?? ''); ?>" 
                           required pattern="[a-z0-9\-]+" 
                           style="border-radius:0 8px 8px 0; direction:ltr; flex:1;"
                           placeholder="<?php echo esc_attr__('your-store-name', 'vmp'); ?>">
                </div>
                <div class="vmp-input-hint">
                    <?php echo esc_html__('أحرف إنجليزية صغيرة، أرقام، وشرطات فقط. مثال: my-store', 'vmp'); ?>
                </div>
                <div id="vmp-slug-status" style="margin-top:6px; font-size:13px;"></div>
            </div>

            <div class="vmp-form-group">
                <label><?php echo esc_html__('وصف المتجر', 'vmp'); ?></label>
                <textarea name="store_description" class="vmp-textarea" rows="4"><?php echo esc_textarea($vendor->store_description ?? ''); ?></textarea>
                <div class="vmp-input-hint"><?php echo esc_html__('نبذة مختصرة تظهر في صفحة متجرك.', 'vmp'); ?></div>
            </div>

            <!-- قسم: الميزات الإضافية (حسب الخطة) -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">✨</span>
                <?php echo esc_html__('ميزات متقدمة', 'vmp'); ?>
                <span class="vmp-badge-plan"><?php echo $plan ? esc_html($plan->name) : esc_html__('مجاني', 'vmp'); ?></span>
            </div>

            <?php if ($has_address) : ?>
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('عنوان المتجر', 'vmp'); ?></label>
                    <input type="text" name="store_address" class="vmp-input" value="<?php echo esc_attr($vendor->store_address ?? ''); ?>" placeholder="<?php echo esc_attr__('مثال: صنعاء، شارع التعاون', 'vmp'); ?>">
                    <div class="vmp-input-hint">📍 <?php echo esc_html__('سيظهر العنوان مع خريطة في صفحة متجرك.', 'vmp'); ?></div>
                </div>
                <div class="vmp-form-row">
                    <div class="vmp-form-group">
                        <label><?php echo esc_html__('خط العرض (Latitude)', 'vmp'); ?></label>
                        <input type="text" name="store_latitude" class="vmp-input" value="<?php echo esc_attr($vendor->store_latitude ?? ''); ?>" placeholder="15.369445">
                    </div>
                    <div class="vmp-form-group">
                        <label><?php echo esc_html__('خط الطول (Longitude)', 'vmp'); ?></label>
                        <input type="text" name="store_longitude" class="vmp-input" value="<?php echo esc_attr($vendor->store_longitude ?? ''); ?>" placeholder="44.191027">
                    </div>
                </div>
            <?php else : ?>
                <div class="vmp-form-group vmp-field-locked">
                    <label class="vmp-label-disabled"><?php echo esc_html__('عنوان المتجر', 'vmp'); ?> <span class="vmp-lock-icon" title="<?php echo esc_attr__('ميزة العنوان متاحة في الخطط المدفوعة. قم بترقية خطتك.', 'vmp'); ?>">🔒</span></label>
                    <input type="text" class="vmp-input" disabled placeholder="<?php echo esc_attr__('مثال: صنعاء، شارع التعاون', 'vmp'); ?>">
                    <div class="vmp-input-hint vmp-hint-locked">🔒 <?php echo esc_html__('ميزة العنوان متاحة في الخطط المدفوعة. قم بترقية خطتك.', 'vmp'); ?></div>
                </div>
                <div class="vmp-form-row vmp-field-locked">
                    <div class="vmp-form-group">
                        <label class="vmp-label-disabled"><?php echo esc_html__('خط العرض (Latitude)', 'vmp'); ?> <span class="vmp-lock-icon">🔒</span></label>
                        <input type="text" class="vmp-input" disabled placeholder="15.369445">
                    </div>
                    <div class="vmp-form-group">
                        <label class="vmp-label-disabled"><?php echo esc_html__('خط الطول (Longitude)', 'vmp'); ?> <span class="vmp-lock-icon">🔒</span></label>
                        <input type="text" class="vmp-input" disabled placeholder="44.191027">
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($has_social) : ?>
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('روابط التواصل الاجتماعي', 'vmp'); ?></label>
                    <div class="vmp-form-row">
                        <input type="url" name="social_facebook" class="vmp-input" value="<?php echo esc_url($vendor->social_facebook ?? ''); ?>" placeholder="🔵 Facebook">
                        <input type="url" name="social_instagram" class="vmp-input" value="<?php echo esc_url($vendor->social_instagram ?? ''); ?>" placeholder="🟣 Instagram">
                    </div>
                    <div class="vmp-form-row" style="margin-top:10px;">
                        <input type="url" name="social_twitter" class="vmp-input" value="<?php echo esc_url($vendor->social_twitter ?? ''); ?>" placeholder="🐦 Twitter">
                        <input type="url" name="social_youtube" class="vmp-input" value="<?php echo esc_url($vendor->social_youtube ?? ''); ?>" placeholder="▶️ YouTube">
                    </div>
                </div>
            <?php else : ?>
                <div class="vmp-form-group vmp-field-locked">
                    <label class="vmp-label-disabled"><?php echo esc_html__('روابط التواصل الاجتماعي', 'vmp'); ?> <span class="vmp-lock-icon" title="<?php echo esc_attr__('ميزة روابط التواصل متاحة في الخطط المدفوعة.', 'vmp'); ?>">🔒</span></label>
                    <div class="vmp-form-row">
                        <input type="url" class="vmp-input" disabled placeholder="🔵 Facebook">
                        <input type="url" class="vmp-input" disabled placeholder="🟣 Instagram">
                    </div>
                    <div class="vmp-form-row" style="margin-top:10px;">
                        <input type="url" class="vmp-input" disabled placeholder="🐦 Twitter">
                        <input type="url" class="vmp-input" disabled placeholder="▶️ YouTube">
                    </div>
                    <div class="vmp-input-hint vmp-hint-locked">🔒 <?php echo esc_html__('ميزة روابط التواصل متاحة في الخطط المدفوعة.', 'vmp'); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($has_video) : ?>
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('فيديو تعريفي', 'vmp'); ?></label>
                    <input type="url" name="store_video" class="vmp-input" value="<?php echo esc_url($vendor->store_video ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=...">
                    <div class="vmp-input-hint">🎬 <?php echo esc_html__('رابط YouTube أو Vimeo سيظهر في متجرك.', 'vmp'); ?></div>
                </div>
            <?php else : ?>
                <div class="vmp-form-group vmp-field-locked">
                    <label class="vmp-label-disabled"><?php echo esc_html__('فيديو تعريفي', 'vmp'); ?> <span class="vmp-lock-icon" title="<?php echo esc_attr__('ميزة الفيديو متاحة في الخطط المدفوعة.', 'vmp'); ?>">🔒</span></label>
                    <input type="url" class="vmp-input" disabled placeholder="https://www.youtube.com/watch?v=...">
                    <div class="vmp-input-hint vmp-hint-locked">🔒 <?php echo esc_html__('ميزة الفيديو متاحة في الخطط المدفوعة.', 'vmp'); ?></div>
                </div>
            <?php endif; ?>

            <!-- قسم: واتساب -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">💬</span>
                <?php echo esc_html__('إعدادات واتساب', 'vmp'); ?>
            </div>

            <div class="vmp-form-group">
                <label><?php echo esc_html__('رقم واتساب', 'vmp'); ?></label>
                <input type="tel" name="whatsapp_number" class="vmp-input" value="<?php echo esc_attr($vendor->whatsapp_number ?? ''); ?>" dir="ltr" placeholder="+966500000000">
                <div class="vmp-input-hint">💬 <?php echo esc_html__('سيظهر زر "طلب عبر واتساب" في صفحة متجرك ومنتجاتك.', 'vmp'); ?></div>
            </div>

            <!-- قسم: الصور -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">🖼️</span>
                <?php echo esc_html__('صور المتجر', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('شعار المتجر', 'vmp'); ?></label>
                    <div class="vmp-image-upload" data-type="logo">
                        <input type="hidden" name="store_logo" value="<?php echo esc_attr($vendor->store_logo ?? 0); ?>">
                        <?php if (!empty($logo_url)) : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" class="vmp-image-preview show" alt="<?php echo esc_attr__('شعار المتجر', 'vmp'); ?>">
                        <?php else : ?>
                            <img src="" class="vmp-image-preview" alt="<?php echo esc_attr__('شعار المتجر', 'vmp'); ?>">
                        <?php endif; ?>
                        <div class="upload-icon" style="<?php echo !empty($logo_url) ? 'display:none;' : ''; ?>">📸</div>
                        <p style="<?php echo !empty($logo_url) ? 'display:none;' : ''; ?>"><?php echo esc_html__('انقر لاختيار صورة', 'vmp'); ?></p>
                        <button type="button" class="vmp-remove-image" style="<?php echo empty($logo_url) ? 'display:none;' : ''; ?>" title="<?php echo esc_attr__('إزالة الصورة', 'vmp'); ?>">✕</button>
                    </div>
                </div>
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('غلاف المتجر', 'vmp'); ?></label>
                    <div class="vmp-image-upload" data-type="banner">
                        <input type="hidden" name="store_banner" value="<?php echo esc_attr($vendor->store_banner ?? 0); ?>">
                        <?php if (!empty($banner_url)) : ?>
                            <img src="<?php echo esc_url($banner_url); ?>" class="vmp-image-preview show" alt="<?php echo esc_attr__('غلاف المتجر', 'vmp'); ?>">
                        <?php else : ?>
                            <img src="" class="vmp-image-preview" alt="<?php echo esc_attr__('غلاف المتجر', 'vmp'); ?>">
                        <?php endif; ?>
                        <div class="upload-icon" style="<?php echo !empty($banner_url) ? 'display:none;' : ''; ?>">🖼️</div>
                        <p style="<?php echo !empty($banner_url) ? 'display:none;' : ''; ?>"><?php echo esc_html__('انقر لاختيار غلاف (يفضل 1200x400)', 'vmp'); ?></p>
                        <button type="button" class="vmp-remove-image" style="<?php echo empty($banner_url) ? 'display:none;' : ''; ?>" title="<?php echo esc_attr__('إزالة الصورة', 'vmp'); ?>">✕</button>
                    </div>
                </div>
            </div>

            <!-- قسم: الحساب -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">👤</span>
                <?php echo esc_html__('بيانات الحساب', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('الاسم الأول', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="first_name" class="vmp-input" value="<?php echo esc_attr($user->first_name ?? ''); ?>" required>
                </div>
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('الاسم الأخير', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="last_name" class="vmp-input" value="<?php echo esc_attr($user->last_name ?? ''); ?>" required>
                </div>
            </div>

            <div class="vmp-form-group">
                <label><?php echo esc_html__('البريد الإلكتروني', 'vmp'); ?> <span class="required">*</span></label>
                <input type="email" name="store_email" class="vmp-input" value="<?php echo esc_attr($user->user_email ?? ''); ?>" required>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('كلمة المرور الجديدة', 'vmp'); ?></label>
                    <input type="password" name="password" class="vmp-input" placeholder="••••••••" autocomplete="new-password">
                    <div class="vmp-input-hint">🔑 <?php echo esc_html__('اتركه فارغاً إذا لم ترغب في التغيير.', 'vmp'); ?></div>
                </div>
                <div class="vmp-form-group">
                    <label><?php echo esc_html__('تأكيد كلمة المرور', 'vmp'); ?></label>
                    <input type="password" name="confirm_password" class="vmp-input" placeholder="••••••••" autocomplete="new-password">
                    <div class="vmp-input-hint">🔑 <?php echo esc_html__('أعد كتابة كلمة المرور للتأكيد.', 'vmp'); ?></div>
                </div>
            </div>

            <div style="margin-top:30px;">
                <button type="submit" class="vmp-btn vmp-btn-primary vmp-btn-lg">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php echo esc_html__('حفظ التعديلات', 'vmp'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="vmp-loading"><div class="vmp-spinner"></div></div>




