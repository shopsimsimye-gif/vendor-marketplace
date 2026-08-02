<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من البائع ──
$user_id = get_current_user_id();
if (!$user_id) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('يجب تسجيل الدخول أولاً.', 'vmp') . '</div>';
    return;
}

$vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
$vendor = $vendor_repo->findByUserId($user_id);

if (!$vendor) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('البائع غير موجود.', 'vmp') . '</div>';
    return;
}
if ($vendor->status !== 'approved') {
    echo '<div class="vmp-notice vmp-notice-warning">' . __('حسابك قيد المراجعة أو غير معتمد.', 'vmp') . '</div>';
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

$logo_url   = $vendor->store_logo ? wp_get_attachment_url($vendor->store_logo) : '';
$banner_url = $vendor->store_banner ? wp_get_attachment_url($vendor->store_banner) : '';

$user = wp_get_current_user();
$nav_file = VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php';

// ── بناء رابط المتجر ──
$store_base = get_option('vmp_store_base', 'store');
$store_slug = !empty($vendor->store_slug) ? $vendor->store_slug : sanitize_title($vendor->store_name);
if (empty($store_slug)) {
    $store_slug = 'store-' . $vendor->id;
}
$store_url = home_url('/' . $store_base . '/' . $store_slug . '/');
?>

<div class="vmp-wrap">
    <!-- التنقل -->
    <?php if (file_exists($nav_file)) include $nav_file; ?>

    <div class="vmp-card" style="max-width: 820px; margin: 0 auto;">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php _e('إعدادات المتجر', 'vmp'); ?></h2>
            <span class="vmp-badge-status vmp-status-approved"><?php _e('نشط', 'vmp'); ?></span>
        </div>

        <!-- ── عرض رابط المتجر الحالي ── -->
        <div class="vmp-store-url-box" style="background: #f8fafc; border: 2px solid #2563eb; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
            <div class="vmp-store-url-label" style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 16px; color: #1e293b;">
                <span class="dashicons dashicons-admin-links" style="font-size: 24px; width: 24px; height: 24px;"></span>
                <?php _e('رابط متجرك:', 'vmp'); ?>
            </div>
            <div class="vmp-store-url-display" style="flex: 1; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <input type="text" id="vmp-store-url-input" value="<?php echo esc_url($store_url); ?>" readonly 
                       style="flex: 1; min-width: 200px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: #ffffff; color: #1e293b; direction: ltr; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
                <button class="vmp-btn vmp-btn-sm vmp-copy-url-btn" data-url="<?php echo esc_url($store_url); ?>" 
                        style="display: inline-flex; align-items: center; gap: 4px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; background: #2563eb; color: #ffffff; transition: 0.2s;">
                    <span class="dashicons dashicons-clipboard" style="font-size: 18px; width: 18px; height: 18px;"></span> <?php _e('نسخ', 'vmp'); ?>
                </button>
            </div>
            <div class="vmp-store-url-hint" style="width: 100%; font-size: 13px; color: #64748b; margin-top: 4px;">
                <?php _e('استخدم هذا الرابط لمشاركة متجرك مع العملاء.', 'vmp'); ?>
            </div>
        </div>

        <form id="vmp-profile-form" class="vmp-profile-form" data-action="vmp_vendor_update_profile">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('vmp_public_nonce'); ?>">

            <!-- قسم: معلومات المتجر الأساسية -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">🏪</span>
                <?php _e('معلومات المتجر', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('اسم المتجر', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="store_name" class="vmp-input" value="<?php echo esc_attr($vendor->store_name ?? ''); ?>" required>
                </div>
                <div class="vmp-form-group">
                    <label><?php _e('رقم الهاتف', 'vmp'); ?></label>
                    <input type="tel" name="store_phone" class="vmp-input" value="<?php echo esc_attr($vendor->store_phone ?? ''); ?>" dir="ltr" placeholder="+966 500 000 000">
                </div>
            </div>

            <!-- ── رابط المتجر (Slug) ── -->
            <div class="vmp-form-group">
                <label><?php _e('رابط المتجر (Slug)', 'vmp'); ?> <span class="required">*</span></label>
                <div style="display:flex; align-items:center; direction:ltr;">
                    <span style="background:var(--vmp-border); padding:11px 14px; border-radius:8px 0 0 8px; font-size:13px; color:var(--vmp-text-muted); border:1.5px solid var(--vmp-border); border-right:none;">
                        <?php echo esc_url(home_url('/' . $store_base . '/')); ?>
                    </span>
                    <input type="text" name="store_slug" class="vmp-input" value="<?php echo esc_attr($vendor->store_slug ?? ''); ?>" 
                           required pattern="[a-z0-9\-]+" 
                           style="border-radius:0 8px 8px 0; direction:ltr; flex:1;"
                           placeholder="<?php _e('your-store-name', 'vmp'); ?>">
                </div>
                <div class="vmp-input-hint">
                    <?php _e('أحرف إنجليزية صغيرة، أرقام، وشرطات فقط. مثال: my-store', 'vmp'); ?>
                </div>
                <div id="vmp-slug-status" style="margin-top:6px; font-size:13px;"></div>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('وصف المتجر', 'vmp'); ?></label>
                <textarea name="store_description" class="vmp-textarea" rows="4"><?php echo esc_textarea($vendor->store_description ?? ''); ?></textarea>
                <div class="vmp-input-hint"><?php _e('نبذة مختصرة تظهر في صفحة متجرك.', 'vmp'); ?></div>
            </div>

            <!-- قسم: الميزات الإضافية (حسب الخطة) -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">✨</span>
                <?php _e('ميزات متقدمة', 'vmp'); ?>
                <span class="vmp-badge-plan"><?php echo $plan ? esc_html($plan->name) : __('مجاني', 'vmp'); ?></span>
            </div>

            <?php if ($has_address) : ?>
                <div class="vmp-form-group">
                    <label><?php _e('عنوان المتجر', 'vmp'); ?></label>
                    <input type="text" name="store_address" class="vmp-input" value="<?php echo esc_attr($vendor->store_address ?? ''); ?>" placeholder="<?php _e('مثال: صنعاء، شارع التعاون', 'vmp'); ?>">
                    <div class="vmp-input-hint">📍 <?php _e('سيظهر العنوان مع خريطة في صفحة متجرك.', 'vmp'); ?></div>
                </div>
                <div class="vmp-form-row">
                    <div class="vmp-form-group">
                        <label><?php _e('خط العرض (Latitude)', 'vmp'); ?></label>
                        <input type="text" name="store_latitude" class="vmp-input" value="<?php echo esc_attr($vendor->store_latitude ?? ''); ?>" placeholder="15.369445">
                    </div>
                    <div class="vmp-form-group">
                        <label><?php _e('خط الطول (Longitude)', 'vmp'); ?></label>
                        <input type="text" name="store_longitude" class="vmp-input" value="<?php echo esc_attr($vendor->store_longitude ?? ''); ?>" placeholder="44.191027">
                    </div>
                </div>
            <?php else : ?>
                <div class="vmp-notice vmp-notice-info"><strong>🔒</strong> <?php _e('ميزة العنوان متاحة في الخطط المدفوعة. قم بترقية خطتك.', 'vmp'); ?></div>
            <?php endif; ?>

            <?php if ($has_social) : ?>
                <div class="vmp-form-group">
                    <label><?php _e('روابط التواصل الاجتماعي', 'vmp'); ?></label>
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
                <div class="vmp-notice vmp-notice-info"><strong>🔒</strong> <?php _e('ميزة روابط التواصل متاحة في الخطط المدفوعة.', 'vmp'); ?></div>
            <?php endif; ?>

            <?php if ($has_video) : ?>
                <div class="vmp-form-group">
                    <label><?php _e('فيديو تعريفي', 'vmp'); ?></label>
                    <input type="url" name="store_video" class="vmp-input" value="<?php echo esc_url($vendor->store_video ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=...">
                    <div class="vmp-input-hint">🎬 <?php _e('رابط YouTube أو Vimeo سيظهر في متجرك.', 'vmp'); ?></div>
                </div>
            <?php else : ?>
                <div class="vmp-notice vmp-notice-info"><strong>🔒</strong> <?php _e('ميزة الفيديو متاحة في الخطط المدفوعة.', 'vmp'); ?></div>
            <?php endif; ?>

            <!-- قسم: واتساب -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">💬</span>
                <?php _e('إعدادات واتساب', 'vmp'); ?>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('رقم واتساب', 'vmp'); ?></label>
                <input type="tel" name="whatsapp_number" class="vmp-input" value="<?php echo esc_attr($vendor->whatsapp_number ?? ''); ?>" dir="ltr" placeholder="+966500000000">
                <div class="vmp-input-hint">💬 <?php _e('سيظهر زر "طلب عبر واتساب" في صفحة متجرك ومنتجاتك.', 'vmp'); ?></div>
            </div>

            <!-- قسم: الصور -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">🖼️</span>
                <?php _e('صور المتجر', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('شعار المتجر', 'vmp'); ?></label>
                    <div class="vmp-image-upload">
                        <input type="hidden" name="store_logo" value="<?php echo esc_attr($vendor->store_logo ?? 0); ?>">
                        <?php if (!empty($logo_url)) : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" class="vmp-image-preview show" alt="Logo">
                        <?php else : ?>
                            <img src="" class="vmp-image-preview" alt="Logo">
                        <?php endif; ?>
                        <div class="upload-icon" style="<?php echo !empty($logo_url) ? 'display:none;' : ''; ?>">📸</div>
                        <p style="<?php echo !empty($logo_url) ? 'display:none;' : ''; ?>"><?php _e('انقر لاختيار صورة', 'vmp'); ?></p>
                    </div>
                </div>
                <div class="vmp-form-group">
                    <label><?php _e('غلاف المتجر', 'vmp'); ?></label>
                    <div class="vmp-image-upload">
                        <input type="hidden" name="store_banner" value="<?php echo esc_attr($vendor->store_banner ?? 0); ?>">
                        <?php if (!empty($banner_url)) : ?>
                            <img src="<?php echo esc_url($banner_url); ?>" class="vmp-image-preview show" alt="Banner">
                        <?php else : ?>
                            <img src="" class="vmp-image-preview" alt="Banner">
                        <?php endif; ?>
                        <div class="upload-icon" style="<?php echo !empty($banner_url) ? 'display:none;' : ''; ?>">🖼️</div>
                        <p style="<?php echo !empty($banner_url) ? 'display:none;' : ''; ?>"><?php _e('انقر لاختيار غلاف (يفضل 1200x400)', 'vmp'); ?></p>
                    </div>
                </div>
            </div>

            <!-- قسم: الحساب -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">👤</span>
                <?php _e('بيانات الحساب', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('الاسم الأول', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="first_name" class="vmp-input" value="<?php echo esc_attr($user->first_name ?? ''); ?>" required>
                </div>
                <div class="vmp-form-group">
                    <label><?php _e('الاسم الأخير', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="last_name" class="vmp-input" value="<?php echo esc_attr($user->last_name ?? ''); ?>" required>
                </div>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('البريد الإلكتروني', 'vmp'); ?> <span class="required">*</span></label>
                <input type="email" name="store_email" class="vmp-input" value="<?php echo esc_attr($user->user_email ?? ''); ?>" required>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('كلمة المرور الجديدة', 'vmp'); ?></label>
                <input type="password" name="password" class="vmp-input" placeholder="••••••••">
                <div class="vmp-input-hint">🔑 <?php _e('اتركه فارغاً إذا لم ترغب في التغيير.', 'vmp'); ?></div>
            </div>

            <div style="margin-top:30px;">
                <button type="submit" class="vmp-btn vmp-btn-primary vmp-btn-lg">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('حفظ التعديلات', 'vmp'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="vmp-loading"><div class="vmp-spinner"></div></div>

<style>
.vmp-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 17px;
    font-weight: 700;
    color: var(--vmp-text);
    margin: 28px 0 18px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--vmp-border);
}
.vmp-section-icon { font-size: 20px; }
.vmp-badge-plan {
    margin-right: auto;
    background: var(--vmp-primary-light);
    color: var(--vmp-primary);
    padding: 2px 14px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
}
.vmp-notice-info strong { font-size: 14px; }
.vmp-image-upload {
    border: 2px dashed var(--vmp-border);
    border-radius: var(--vmp-radius);
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: all var(--vmp-transition);
    position: relative;
}
.vmp-image-upload:hover {
    border-color: var(--vmp-primary);
    background: var(--vmp-primary-light);
}
.vmp-image-upload .upload-icon { font-size: 36px; margin-bottom: 10px; }
.vmp-image-upload p { color: var(--vmp-text-muted); font-size: 13px; margin: 0; }
.vmp-image-preview {
    width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: var(--vmp-radius-sm);
    display: none;
}
.vmp-image-preview.show { display: block; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── نسخ الرابط ──
    const copyBtn = document.querySelector('.vmp-copy-url-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const url = this.dataset.url;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    alert('<?php _e('تم نسخ رابط المتجر!', 'vmp'); ?>');
                }).catch(() => {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        });
    }

    function fallbackCopy(text) {
        const input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        alert('<?php _e('تم نسخ رابط المتجر!', 'vmp'); ?>');
    }

    // ── التحقق من تفرد الـ Slug ──
    const slugInput = document.querySelector('input[name="store_slug"]');
    const slugStatus = document.getElementById('vmp-slug-status');
    
    if (slugInput && slugStatus) {
        let timeoutId;
        slugInput.addEventListener('input', function() {
            const slug = this.value.trim();
            if (!slug) {
                slugStatus.innerHTML = '';
                return;
            }
            
            // التحقق من الصيغة
            if (!/^[a-z0-9\-]+$/.test(slug)) {
                slugStatus.innerHTML = '⚠️ <?php _e('أحرف إنجليزية صغيرة، أرقام، وشرطات فقط.', 'vmp'); ?>';
                slugStatus.style.color = '#f59e0b';
                return;
            }
            
            clearTimeout(timeoutId);
            timeoutId = setTimeout(function() {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'vmp_check_store_slug',
                        nonce: '<?php echo wp_create_nonce('vmp_vendor_check_slug'); ?>',
                        slug: slug,
                        exclude_user_id: <?php echo (int) $user_id; ?>
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.available) {
                        slugStatus.innerHTML = '✅ <?php _e('الرابط متاح', 'vmp'); ?>';
                        slugStatus.style.color = '#10b981';
                    } else {
                        slugStatus.innerHTML = '❌ <?php _e('الرابط مستخدم مسبقاً', 'vmp'); ?>';
                        slugStatus.style.color = '#ef4444';
                    }
                })
                .catch(() => {
                    slugStatus.innerHTML = '⚠️ <?php _e('تعذر التحقق', 'vmp'); ?>';
                    slugStatus.style.color = '#f59e0b';
                });
            }, 500);
        });
    }

    // ── رفع الصور (Media Uploader) ──
    document.querySelectorAll('.vmp-image-upload').forEach(function(container) {
        const input = container.querySelector('input[type="hidden"]');
        const preview = container.querySelector('.vmp-image-preview');
        const icon = container.querySelector('.upload-icon');
        const text = container.querySelector('p');

        container.addEventListener('click', function(e) {
            e.preventDefault();

            const frame = wp.media({
                title: '<?php _e('اختر صورة', 'vmp'); ?>',
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                input.value = attachment.id;
                preview.src = attachment.url;
                preview.classList.add('show');
                if (icon) icon.style.display = 'none';
                if (text) text.style.display = 'none';
            });

            frame.open();
        });
    });

    // ── إرسال النموذج عبر AJAX ──
    const form = document.getElementById('vmp-profile-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<?php _e('جاري الحفظ...', 'vmp'); ?>';

            const formData = new FormData(this);
            formData.append('action', this.dataset.action || 'vmp_vendor_update_profile');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(res => res.json())
            .then(data => {
                const payload = data.data || {};
                if (data.success) {
                    alert(payload.message || data.message || '<?php _e('تم حفظ التغييرات بنجاح.', 'vmp'); ?>');
                    // تحديث الرابط المعروض إذا تغير الـ slug
                    if (payload.store_slug) {
                        const urlInput = document.getElementById('vmp-store-url-input');
                        if (urlInput) {
                            const base = '<?php echo esc_url(home_url('/' . $store_base . '/')); ?>';
                            urlInput.value = base + payload.store_slug + '/';
                        }
                    }
                    // تحديث زر النسخ بالرابط الجديد
                    const copyBtn = document.querySelector('.vmp-copy-url-btn');
                    if (copyBtn && payload.store_slug) {
                        const base = '<?php echo esc_url(home_url('/' . $store_base . '/')); ?>';
                        copyBtn.dataset.url = base + payload.store_slug + '/';
                    }
                } else {
                    alert(payload.message || data.message || '<?php _e('حدث خطأ أثناء الحفظ.', 'vmp'); ?>');
                }
            })
            .catch(() => {
                alert('<?php _e('خطأ في الاتصال بالخادم.', 'vmp'); ?>');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<?php _e('حفظ التعديلات', 'vmp'); ?>';
            });
        });
    }
});
</script>
