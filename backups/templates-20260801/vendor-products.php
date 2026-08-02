<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── الحصول على البائع الحالي ──
$vendor_id = \VMP\Support\VendorHelper::get_current_vendor_id();
if (!$vendor_id) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('يجب تسجيل الدخول كبائع معتمد.', 'vmp') . '</div>';
    return;
}

$vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
$vendor = $vendor_repo->find($vendor_id);

if (!$vendor || $vendor->status !== 'approved') {
    echo '<div class="vmp-notice vmp-notice-error">' . __('يجب أن تكون بائعاً معتمداً للوصول إلى هذه الصفحة.', 'vmp') . '</div>';
    return;
}

// ── استخدام Repository Pattern ──
$product_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\ProductRepositoryInterface::class);
$plan_repo    = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);
$sub_repo     = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);

// ── الحصول على الخطة الحالية ──
$active_sub = $sub_repo->findActiveByVendor($vendor->id);
$plan = $active_sub
    ? $plan_repo->find($active_sub->plan_id)
    : $plan_repo->findBySlug('free');

$max_products = $plan ? (int) $plan->max_products : 10;
$current_count = $product_repo->countByVendor($vendor->id);
$can_add = ($max_products === 0) || ($current_count < $max_products);

// ── جلب المنتجات عبر Repository ──
$paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit  = 12;
$offset = ($paged - 1) * $limit;

$products = $product_repo->getByVendor($vendor->id, [
    'limit'  => $limit,
    'offset' => $offset,
    'status' => 'all',
]);

$total = $product_repo->countByVendor($vendor->id);
$pages = (int) ceil($total / $limit);

// ── حل N+1: جلب جميع منتجات WooCommerce دفعة واحدة ──
$wc_products_by_id = [];
if (!empty($products)) {
    $product_ids = array_filter(array_map('intval', wp_list_pluck($products, 'product_id')));
    if (!empty($product_ids)) {
        $wc_products = wc_get_products([
            'include' => $product_ids,
            'limit'   => -1,
        ]);
        foreach ($wc_products as $wc_product) {
            $wc_products_by_id[$wc_product->get_id()] = $wc_product;
        }
    }
}

// ── حالات المنتج ──
$status_labels = [
    'pending'  => ['label' => __('قيد المراجعة', 'vmp'), 'class' => 'vmp-status-pending'],
    'approved' => ['label' => __('نشط', 'vmp'), 'class' => 'vmp-status-approved'],
    'rejected' => ['label' => __('مرفوض', 'vmp'), 'class' => 'vmp-status-rejected'],
];
?>

<div class="vmp-wrap">
    <!-- شريط التنقل -->
    <div class="vmp-nav">
        <a href="?vmp_page=dashboard"><?php _e('لوحة التحكم', 'vmp'); ?></a>
        <a href="?vmp_page=products" class="active"><?php _e('المنتجات', 'vmp'); ?></a>
        <a href="?vmp_page=orders"><?php _e('الطلبات', 'vmp'); ?></a>
        <a href="?vmp_page=withdrawals"><?php _e('السحوبات', 'vmp'); ?></a>
        <a href="?vmp_page=profile"><?php _e('إعدادات المتجر', 'vmp'); ?></a>
        <a href="?vmp_page=subscriptions"><?php _e('خطتي', 'vmp'); ?></a>
    </div>

    <div class="vmp-card">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php _e('منتجاتي', 'vmp'); ?> (<?php echo (int) $total; ?>)</h2>
            
            <?php if ($can_add): ?>
                <a href="?vmp_page=ai-create-product" class="vmp-btn vmp-btn-secondary vmp-btn-sm">
                    <?php _e('إنشاء من صورة', 'vmp'); ?>
                </a>
                <a href="?vmp_page=add-product" class="vmp-btn vmp-btn-primary vmp-btn-sm">
                    + <?php _e('إضافة منتج', 'vmp'); ?>
                </a>
            <?php else: ?>
                <span class="vmp-badge-status vmp-status-warning">
                    <?php _e('وصلت للحد الأقصى للمنتجات', 'vmp'); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="vmp-table-wrap">
            <table class="vmp-table">
                <thead>
                    <tr>
                        <th><?php _e('الصورة', 'vmp'); ?></th>
                        <th><?php _e('اسم المنتج', 'vmp'); ?></th>
                        <th><?php _e('السعر', 'vmp'); ?></th>
                        <th><?php _e('المخزون', 'vmp'); ?></th>
                        <th><?php _e('الحالة', 'vmp'); ?></th>
                        <th><?php _e('إجراءات', 'vmp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="vmp-empty">
                                    <div class="vmp-empty-icon">📦</div>
                                    <h3><?php _e('لا توجد منتجات', 'vmp'); ?></h3>
                                    <p><?php _e('لم تقم بإضافة أي منتجات بعد.', 'vmp'); ?></p>
                                    <?php if ($can_add): ?>
                                        <a href="?vmp_page=add-product" class="vmp-btn vmp-btn-primary">
                                            <?php _e('إضافة أول منتج', 'vmp'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): 
                            $wc_product = $wc_products_by_id[(int) $p->product_id] ?? null;
                            $badge = $status_labels[$p->status] ?? ['label' => $p->status, 'class' => ''];
                        ?>
                            <tr>
                                <td style="width: 60px;">
                                    <?php if ($wc_product): ?>
                                        <?php echo $wc_product->get_image('thumbnail', ['style' => 'width:40px; height:40px; border-radius:6px; object-fit:cover;']); ?>
                                    <?php else: ?>
                                        <div style="width:40px; height:40px; border-radius:6px; background:var(--vmp-border);"></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($wc_product): ?>
                                        <strong><?php echo esc_html($wc_product->get_name()); ?></strong>
                                    <?php else: ?>
                                        <span style="color:red;"><?php _e('منتج محذوف', 'vmp'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($wc_product): ?>
                                        <?php echo $wc_product->get_price_html(); ?>
                                    <?php else: ?>
                                        <span style="color:red;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($wc_product && $wc_product->managing_stock()): ?>
                                        <?php echo (int) $wc_product->get_stock_quantity(); ?>
                                    <?php else: ?>
                                        <span style="color:var(--vmp-success);"><?php _e('متوفر', 'vmp'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="vmp-badge-status <?php echo esc_attr($badge['class']); ?>"><?php echo esc_html($badge['label']); ?></span></td>
                                <td>
                                    <?php if ($wc_product): ?>
                                        <div class="vmp-actions" style="display:flex; gap:6px; flex-wrap:wrap;">
                                            <a href="<?php echo esc_url(get_permalink((int) $p->product_id)); ?>" target="_blank" class="vmp-btn vmp-btn-outline vmp-btn-sm">
                                                <?php _e('عرض', 'vmp'); ?>
                                            </a>
                                            <a href="?vmp_page=edit-product&id=<?php echo (int) $p->id; ?>" class="vmp-btn vmp-btn-secondary vmp-btn-sm">
                                                <?php _e('تعديل', 'vmp'); ?>
                                            </a>
                                            <button class="vmp-btn vmp-btn-danger vmp-btn-sm vmp-delete-product" 
                                                    data-product-id="<?php echo (int) $p->id; ?>">
                                                <?php _e('حذف', 'vmp'); ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="vmp-pagination">
                <?php
                echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $pages,
                    'current'   => $paged,
                ]);
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>