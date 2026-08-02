<?php
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
$vendor = $vendor_repo->findByUserId($user_id);

if (!$vendor || $vendor->status !== 'approved') {
    echo '<p>' . __('يجب أن تكون بائعاً معتمداً للوصول إلى هذه الصفحة.', 'vmp') . '</p>';
    return;
}

// التحقق من توفر ميزة إنشاء المنتج بالذكاء الاصطناعي
$subscription_service = \VMP\Core\Container::getInstance()->make(\VMP\Services\SubscriptionService::class);
if (!$subscription_service->hasFeature((int) $vendor->id, 'ai_product_generator')) {
    echo '<div class="vmp-wrap vmp-ai-product-page"><div class="vmp-card"><div class="vmp-card-body"><p class="vmp-notice vmp-notice-warning">' . __('ميزة إنشاء المنتج بالذكاء الاصطناعي غير متاحة في خطة الاشتراك الحالية.', 'vmp') . '</p></div></div></div>';
    return;
}

$nav_file = VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php';
?>

<div class="vmp-wrap vmp-ai-product-page">
    <?php if (file_exists($nav_file)) include $nav_file; ?>

    <div class="vmp-card">
        <div class="vmp-card-header">
            <div>
                <h2 class="vmp-card-title"><?php _e('إنشاء منتج من صورة', 'vmp'); ?></h2>
                <p class="vmp-muted"><?php _e('ارفع صورة واحدة، ثم راجع المسودة قبل إرسالها للنشر.', 'vmp'); ?></p>
            </div>
            <a href="?vmp_page=products" class="vmp-btn vmp-btn-outline vmp-btn-sm"><?php _e('عودة للمنتجات', 'vmp'); ?></a>
        </div>

        <div class="vmp-ai-layout">
            <form id="vmp-ai-product-upload" class="vmp-ai-upload-panel" enctype="multipart/form-data">
                <input type="hidden" name="action" value="vmp_ai_create_product_from_image">
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('vmp_public_nonce')); ?>">
                <input type="hidden" name="workflow_id" value="product-image-v1">

                <label class="vmp-ai-dropzone" for="vmp-ai-product-image">
                    <span class="vmp-ai-dropzone-icon">+</span>
                    <strong><?php _e('اختر صورة المنتج', 'vmp'); ?></strong>
                    <small><?php _e('JPG أو PNG، ويفضل صورة واضحة بخلفية بسيطة.', 'vmp'); ?></small>
                    <input id="vmp-ai-product-image" name="image" type="file" accept="image/*" required>
                </label>

                <div id="vmp-ai-image-preview" class="vmp-ai-image-preview" hidden></div>

                <button type="submit" class="vmp-btn vmp-btn-primary vmp-btn-block">
                    <?php _e('إنشاء المسودة', 'vmp'); ?>
                </button>
            </form>

            <div class="vmp-ai-progress-panel">
                <div class="vmp-ai-progress-header">
                    <strong><?php _e('حالة المعالجة', 'vmp'); ?></strong>
                    <span id="vmp-ai-progress-percent">0%</span>
                </div>
                <div class="vmp-ai-progress-bar">
                    <span id="vmp-ai-progress-fill" style="width:0%"></span>
                </div>
                <ol class="vmp-ai-steps">
                    <li data-step="UPLOADED" class="active"><?php _e('رفع الصورة', 'vmp'); ?></li>
                    <li data-step="QUEUED"><?php _e('في الطابور', 'vmp'); ?></li>
                    <li data-step="ANALYZING_IMAGE"><?php _e('تحليل الصورة', 'vmp'); ?></li>
                    <li data-step="SEARCHING"><?php _e('البحث وجمع المواصفات', 'vmp'); ?></li>
                    <li data-step="GENERATING_TITLE"><?php _e('توليد العنوان', 'vmp'); ?></li>
                    <li data-step="GENERATING_DESCRIPTION"><?php _e('توليد الوصف', 'vmp'); ?></li>
                    <li data-step="GENERATING_SEO"><?php _e('تحسين SEO', 'vmp'); ?></li>
                    <li data-step="REVIEW"><?php _e('المراجعة', 'vmp'); ?></li>
                </ol>
                <div id="vmp-ai-status-message" class="vmp-ai-status-message">
                    <?php _e('ابدأ برفع صورة المنتج.', 'vmp'); ?>
                </div>
            </div>
        </div>
    </div>

    <div id="vmp-ai-review-card" class="vmp-card vmp-ai-review-card" hidden>
        <div class="vmp-card-header">
            <div>
                <h2 class="vmp-card-title"><?php _e('مراجعة المسودة', 'vmp'); ?></h2>
                <p class="vmp-muted"><?php _e('يمكنك تعديل الحقول أو إعادة توليد جزء محدد فقط.', 'vmp'); ?></p>
            </div>
            <span id="vmp-ai-confidence" class="vmp-ai-confidence">0%</span>
        </div>

        <form id="vmp-ai-review-form">
            <input type="hidden" id="vmp-ai-job-id" name="job_id" value="">

            <div class="vmp-form-group">
                <label for="vmp-ai-title"><?php _e('العنوان', 'vmp'); ?></label>
                <div class="vmp-ai-inline-action">
                    <input id="vmp-ai-title" name="title" type="text" class="vmp-input">
                    <button type="button" class="vmp-btn vmp-btn-outline vmp-btn-sm vmp-ai-regenerate" data-part="title">
                        <?php _e('إعادة توليد', 'vmp'); ?>
                    </button>
                </div>
            </div>

            <div class="vmp-form-group">
                <label for="vmp-ai-description"><?php _e('الوصف', 'vmp'); ?></label>
                <textarea id="vmp-ai-description" name="description" class="vmp-textarea" rows="7"></textarea>
                <div class="vmp-ai-field-tools">
                    <label><input type="checkbox" class="vmp-ai-lock" data-part="description"> <?php _e('احتفظ بالوصف', 'vmp'); ?></label>
                    <button type="button" class="vmp-btn vmp-btn-outline vmp-btn-sm vmp-ai-regenerate" data-part="description">
                        <?php _e('إعادة توليد الوصف فقط', 'vmp'); ?>
                    </button>
                </div>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label for="vmp-ai-price"><?php _e('السعر', 'vmp'); ?></label>
                    <input id="vmp-ai-price" name="regular_price" type="number" min="0" step="0.01" class="vmp-input" value="0">
                </div>
                <div class="vmp-form-group">
                    <label for="vmp-ai-short-description"><?php _e('وصف قصير', 'vmp'); ?></label>
                    <input id="vmp-ai-short-description" name="short_description" type="text" class="vmp-input">
                </div>
            </div>

            <div class="vmp-ai-review-grid">
                <section>
                    <div class="vmp-ai-section-title">
                        <strong><?php _e('المواصفات', 'vmp'); ?></strong>
                        <label><input type="checkbox" class="vmp-ai-lock" data-part="specifications" checked> <?php _e('احتفظ بها', 'vmp'); ?></label>
                    </div>
                    <div id="vmp-ai-specifications" class="vmp-ai-tags"></div>
                </section>
                <section>
                    <div class="vmp-ai-section-title">
                        <strong><?php _e('الكلمات المفتاحية', 'vmp'); ?></strong>
                        <button type="button" class="vmp-btn vmp-btn-outline vmp-btn-sm vmp-ai-regenerate" data-part="keywords">
                            <?php _e('إعادة توليد', 'vmp'); ?>
                        </button>
                    </div>
                    <div id="vmp-ai-keywords" class="vmp-ai-tags"></div>
                </section>
            </div>

            <div class="vmp-ai-meta">
                <span><?php _e('المزود', 'vmp'); ?>: <strong id="vmp-ai-provider">-</strong></span>
                <span><?php _e('الوقت', 'vmp'); ?>: <strong id="vmp-ai-latency">0ms</strong></span>
                <span><?php _e('التكلفة', 'vmp'); ?>: <strong id="vmp-ai-cost">$0.000</strong></span>
                <span><?php _e('الرموز', 'vmp'); ?>: <strong id="vmp-ai-tokens">0</strong></span>
            </div>

            <div id="vmp-ai-warnings" class="vmp-ai-warnings" hidden></div>

            <div class="vmp-form-actions">
                <button type="submit" class="vmp-btn vmp-btn-primary">
                    <?php _e('إرسال المنتج للمراجعة', 'vmp'); ?>
                </button>
                <a href="?vmp_page=products" class="vmp-btn vmp-btn-outline"><?php _e('إلغاء', 'vmp'); ?></a>
            </div>
        </form>
    </div>
</div>
