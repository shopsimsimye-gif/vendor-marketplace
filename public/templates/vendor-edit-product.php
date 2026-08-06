<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من البائع ──
$user_id = get_current_user_id();
$vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
$vendor = $vendor_repo->findByUserId($user_id);

if (!$vendor || $vendor->status !== 'approved') {
    echo '<div class="vmp-notice vmp-notice-error">' . __('يجب أن تكون بائعاً معتمداً لتعديل منتج.', 'vmp') . '</div>';
    return;
}

// ── جلب معرف المنتج من الرابط ──
$vendor_product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$vendor_product_id) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('معرف المنتج غير صالح.', 'vmp') . '</div>';
    return;
}

// ── جلب بيانات المنتج من جدول vmp_vendor_products ──
$product_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\ProductRepositoryInterface::class);
$vendor_product = $product_repo->find($vendor_product_id);

if (!$vendor_product || $vendor_product->vendor_id != $vendor->id) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('المنتج غير موجود أو لا تملك صلاحية تعديله.', 'vmp') . '</div>';
    return;
}

// ── جلب منتج WooCommerce ──
$wc_product = wc_get_product($vendor_product->product_id);
if (!$wc_product) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('المنتج غير موجود في WooCommerce.', 'vmp') . '</div>';
    return;
}

// ── جلب التصنيفات ──
$categories = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
]);

// ── نسبة العمولة (للعرض فقط) ──
$plan_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);
$sub_repo  = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
$active_sub = $sub_repo->findActiveByVendor($vendor->id);
$plan = $active_sub ? $plan_repo->find($active_sub->plan_id) : $plan_repo->findBySlug('free');
$commission_rate = $plan ? (float) $plan->commission_rate : 10;
?>

<div class="vmp-wrap">
    <?php if ( file_exists(VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php') ) : ?>
        <?php include VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php'; ?>
    <?php else : ?>
        <div class="vmp-notice vmp-notice-warning"><?php _e('ملف التنقل غير موجود.', 'vmp'); ?></div>
    <?php endif; ?>

    <div class="vmp-card" style="max-width: 800px; margin: 0 auto;">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php _e('تعديل المنتج', 'vmp'); ?></h2>
            <a href="?vmp_page=products" class="vmp-btn vmp-btn-outline vmp-btn-sm"><?php _e('عودة للمنتجات', 'vmp'); ?></a>
        </div>

        <form class="vmp-ajax-form" data-action="vmp_update_product">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('vmp_public_nonce'); ?>">
            <!-- ✅ إضافة hidden fields لضمان إرسال جميع المعرفات المطلوبة -->
            <input type="hidden" name="vendor_id" value="<?php echo (int) $vendor->id; ?>">
            <input type="hidden" name="vendor_product_id" value="<?php echo (int) $vendor_product->id; ?>">
            <input type="hidden" name="product_id" value="<?php echo (int) $vendor_product->product_id; ?>">

            <div class="vmp-form-group">
                <label><?php _e('اسم المنتج', 'vmp'); ?> <span class="required">*</span></label>
                <input type="text" name="product_name" class="vmp-input" value="<?php echo esc_attr($wc_product->get_name()); ?>" required>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('السعر الأساسي', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="number" step="0.01" name="regular_price" class="vmp-input" value="<?php echo (float) $wc_product->get_regular_price(); ?>" required>
                </div>
                <div class="vmp-form-group">
                    <label><?php _e('سعر التخفيض (اختياري)', 'vmp'); ?></label>
                    <input type="number" step="0.01" name="sale_price" class="vmp-input" value="<?php echo (float) $wc_product->get_sale_price(); ?>">
                </div>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('التصنيف', 'vmp'); ?></label>
                <select name="category" class="vmp-select">
                    <option value=""><?php _e('— اختر التصنيف —', 'vmp'); ?></option>
                    <?php 
                    $current_cats = $wc_product->get_category_ids();
                    foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected(in_array($cat->term_id, $current_cats)); ?>>
                            <?php echo esc_html($cat->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('الوصف القصير', 'vmp'); ?></label>
                <textarea name="short_description" class="vmp-textarea" rows="3"><?php echo esc_textarea($wc_product->get_short_description()); ?></textarea>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('الوصف الكامل', 'vmp'); ?></label>
                <textarea name="description" class="vmp-textarea" rows="6"><?php echo esc_textarea($wc_product->get_description()); ?></textarea>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('إدارة المخزون؟', 'vmp'); ?></label>
                    <select name="manage_stock" class="vmp-select" onchange="document.getElementById('vmp_stock_qty_wrap').style.display = this.value === 'yes' ? 'block' : 'none';">
                        <option value="no" <?php selected(!$wc_product->managing_stock()); ?>><?php _e('لا', 'vmp'); ?></option>
                        <option value="yes" <?php selected($wc_product->managing_stock()); ?>><?php _e('نعم', 'vmp'); ?></option>
                    </select>
                </div>
                <div class="vmp-form-group" id="vmp_stock_qty_wrap" style="<?php echo $wc_product->managing_stock() ? 'block' : 'none'; ?>">
                    <label><?php _e('كمية المخزون', 'vmp'); ?></label>
                    <input type="number" name="stock_quantity" class="vmp-input" value="<?php echo (int) $wc_product->get_stock_quantity(); ?>">
                </div>
            </div>

            <?php
            $current_image_id = (int) $wc_product->get_image_id();
            $current_gallery   = $wc_product->get_gallery_image_ids();
            ?>
            <div class="vmp-form-group">
                <label><?php _e('صورة المنتج الرئيسية', 'vmp'); ?></label>
                <div id="vmp-featured-preview" style="margin-bottom:10px;">
                    <?php if ($current_image_id) : ?>
                        <img src="<?php echo esc_url(wp_get_attachment_url($current_image_id)); ?>" alt="Featured" style="max-width:180px;border-radius:6px;">
                    <?php endif; ?>
                </div>
                <input type="hidden" name="image_id" id="image_id" value="<?php echo esc_attr($current_image_id); ?>">
                <button type="button" id="vmp-select-featured" class="vmp-btn vmp-btn-outline vmp-btn-sm"><?php _e('اختر من المكتبة', 'vmp'); ?></button>
                <button type="button" id="vmp-remove-featured" class="vmp-btn vmp-btn-outline vmp-btn-sm" style="display:<?php echo $current_image_id ? 'inline-block' : 'none'; ?>;color:#b32d2e;"><?php _e('إزالة', 'vmp'); ?></button>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('معرض الصور', 'vmp'); ?></label>
                <div id="vmp-gallery-wrap" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                    <?php foreach ((array) $current_gallery as $gid) : ?>
                        <?php $gid = (int) $gid; if ($gid <= 0) { continue; } ?>
                        <div class="vmp-gallery-item" style="position:relative;display:inline-block;margin:5px;">
                            <input type="hidden" name="gallery_image_ids[]" value="<?php echo esc_attr($gid); ?>">
                            <img src="<?php echo esc_url(wp_get_attachment_url($gid)); ?>" alt="Gallery" style="width:80px;height:80px;object-fit:cover;border-radius:6px;">
                            <button type="button" class="vmp-remove-gallery" style="position:absolute;top:-8px;right:-8px;width:20px;height:20px;background:#b32d2e;color:#fff;border:none;border-radius:50%;cursor:pointer;line-height:20px;text-align:center;">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="vmp-add-gallery" class="vmp-btn vmp-btn-outline vmp-btn-sm"><?php _e('إضافة صور', 'vmp'); ?></button>
            </div>

            <div style="margin-top: 30px;">
                <button type="submit" class="vmp-btn vmp-btn-primary vmp-btn-block vmp-btn-lg">
                    <?php _e('تحديث المنتج', 'vmp'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="vmp-loading"><div class="vmp-spinner"></div></div>




