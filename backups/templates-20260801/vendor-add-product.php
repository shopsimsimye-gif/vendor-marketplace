<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من البائع ──
$user_id = get_current_user_id();
$vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
$vendor = $vendor_repo->findByUserId($user_id);

if (!$vendor || $vendor->status !== 'approved') {
    echo '<div class="vmp-notice vmp-notice-error">' . __('يجب أن تكون بائعاً معتمداً للوصول إلى هذه الصفحة.', 'vmp') . '</div>';
    return;
}

// ── التحقق من الحد الأقصى للمنتجات ──
$plan_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);
$sub_repo  = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);

$active_sub = $sub_repo->findActiveByVendor($vendor->id);
$plan = $active_sub ? $plan_repo->find($active_sub->plan_id) : $plan_repo->findBySlug('free');
$max_products = $plan ? (int) $plan->max_products : 10;

$product_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\ProductRepositoryInterface::class);
$current_count = $product_repo->countByVendor($vendor->id);
$can_add = ($max_products === 0) || ($current_count < $max_products);

if (!$can_add) {
    echo '<div class="vmp-notice vmp-notice-warning">' . __('لقد وصلت للحد الأقصى للمنتجات.', 'vmp') . '</div>';
    return;
}

// ── جلب التصنيفات ──
$categories = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
]);
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
            <h2 class="vmp-card-title"><?php _e('إضافة منتج جديد', 'vmp'); ?></h2>
            <a href="?vmp_page=products" class="vmp-btn vmp-btn-outline vmp-btn-sm"><?php _e('عودة للمنتجات', 'vmp'); ?></a>
        </div>

        <!-- ✅ نموذج AJAX مع الفئات الصحيحة -->
        <form class="vmp-ajax-form" data-action="vmp_add_product" method="post" enctype="multipart/form-data">
            <!-- ✅ Hidden fields الضرورية -->
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('vmp_public_nonce'); ?>">
            <input type="hidden" name="vendor_id" value="<?php echo (int) $vendor->id; ?>">
            <input type="hidden" name="vendor_product_id" value="0">
            <input type="hidden" name="product_id" value="0">

            <div class="vmp-form-group">
                <label><?php _e('اسم المنتج', 'vmp'); ?> <span class="required">*</span></label>
                <input type="text" name="product_name" class="vmp-input" required>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('السعر الأساسي', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="number" step="0.01" name="regular_price" class="vmp-input" required>
                </div>
                <div class="vmp-form-group">
                    <label><?php _e('سعر التخفيض (اختياري)', 'vmp'); ?></label>
                    <input type="number" step="0.01" name="sale_price" class="vmp-input">
                </div>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('التصنيف', 'vmp'); ?></label>
                <select name="category" class="vmp-select">
                    <option value=""><?php _e('— اختر التصنيف —', 'vmp'); ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>">
                            <?php echo esc_html($cat->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('الوصف القصير', 'vmp'); ?></label>
                <textarea name="short_description" class="vmp-textarea" rows="3"></textarea>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('الوصف الكامل', 'vmp'); ?></label>
                <textarea name="description" class="vmp-textarea" rows="6"></textarea>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('إدارة المخزون؟', 'vmp'); ?></label>
                    <select name="manage_stock" class="vmp-select" onchange="document.getElementById('vmp_stock_qty_wrap').style.display = this.value === 'yes' ? 'block' : 'none';">
                        <option value="no"><?php _e('لا', 'vmp'); ?></option>
                        <option value="yes"><?php _e('نعم', 'vmp'); ?></option>
                    </select>
                </div>
                <div class="vmp-form-group" id="vmp_stock_qty_wrap" style="display:none;">
                    <label><?php _e('كمية المخزون', 'vmp'); ?></label>
                    <input type="number" name="stock_quantity" class="vmp-input" value="0">
                </div>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('صورة المنتج الرئيسية', 'vmp'); ?></label>
                <div class="vmp-image-upload">
                    <input type="hidden" name="image_id" value="0">
                    <img src="" class="vmp-image-preview" alt="Preview" style="display:none;">
                    <div class="upload-icon">📸</div>
                    <p><?php _e('انقر لاختيار صورة', 'vmp'); ?></p>
                </div>
            </div>

            <div style="margin-top: 30px;">
                <button type="submit" class="vmp-btn vmp-btn-primary vmp-btn-block vmp-btn-lg">
                    <?php _e('إضافة المنتج', 'vmp'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="vmp-loading"><div class="vmp-spinner"></div></div>

<?php
// ── تحميل مكتبة الوسائط عند الحاجة ──
if (function_exists('wp_enqueue_media')) {
    wp_enqueue_media();
}
?>