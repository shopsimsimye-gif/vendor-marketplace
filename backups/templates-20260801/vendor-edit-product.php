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
    <div class="vmp-nav">
        <a href="?vmp_page=dashboard"><?php _e('لوحة التحكم', 'vmp'); ?></a>
        <a href="?vmp_page=products"><?php _e('المنتجات', 'vmp'); ?></a>
        <a href="?vmp_page=orders"><?php _e('الطلبات', 'vmp'); ?></a>
        <a href="?vmp_page=withdrawals"><?php _e('السحوبات', 'vmp'); ?></a>
        <a href="?vmp_page=profile"><?php _e('إعدادات المتجر', 'vmp'); ?></a>
        <a href="?vmp_page=subscriptions"><?php _e('خطتي', 'vmp'); ?></a>
    </div>

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

            <div class="vmp-form-group">
                <label><?php _e('صورة المنتج الرئيسية', 'vmp'); ?></label>
                <div class="vmp-image-upload">
                    <input type="hidden" name="image_id" value="<?php echo (int) $wc_product->get_image_id(); ?>">
                    <?php 
                    $image_url = wp_get_attachment_url($wc_product->get_image_id());
                    ?>
                    <img src="<?php echo esc_url($image_url); ?>" class="vmp-image-preview <?php echo $image_url ? 'show' : ''; ?>" alt="Preview" style="<?php echo $image_url ? 'display:block;' : 'display:none;'; ?>">
                    <div class="upload-icon" style="<?php echo $image_url ? 'display:none;' : ''; ?>">📸</div>
                    <p style="<?php echo $image_url ? 'display:none;' : ''; ?>"><?php _e('انقر لاختيار صورة', 'vmp'); ?></p>
                </div>
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

<script>
window.vmp_commission_rate = <?php echo $commission_rate; ?>;
</script>

<?php
// ── تحميل مكتبة الوسائط عند الحاجة ──
if (function_exists('wp_enqueue_media')) {
    wp_enqueue_media();
}
?>