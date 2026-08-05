<?php
if ( !defined('ABSPATH') ) { exit; }

// ── التحقق من تسجيل الدخول ووجود البائع ──
$user_id = get_current_user_id();
if ( !$user_id ) {
    echo '<div class="vmp-wrap"><div class="vmp-notice vmp-notice-error">' . esc_html__('يرجى تسجيل الدخول أولاً.', 'vmp') . '</div></div>';
    return;
}

$vendor = vmp_get_vendor(vmp_get_current_vendor_id());
if ( !$vendor ) {
    echo '<div class="vmp-wrap"><div class="vmp-notice vmp-notice-error">' . esc_html__('البائع غير موجود.', 'vmp') . '</div></div>';
    return;
}

if ( $vendor->status !== 'approved' ) {
    echo '<div class="vmp-wrap"><div class="vmp-notice vmp-notice-warning">' . esc_html__('حسابك لم يتم اعتماده بعد.', 'vmp') . '</div></div>';
    return;
}

$vendor_id = (int) $vendor->id;

// ── جلب الطلبات عبر Repository (بدلاً من SQL المباشر في القالب) ──
$order_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\OrderRepositoryInterface::class);

$paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit  = 15;
$offset = ($paged - 1) * $limit;

$orders = $order_repo->getByVendor($vendor_id, [
    'limit'  => $limit,
    'offset' => $offset,
    // '' تعني كل الحالات (حتى لا تُفلتر حسب status='all' غير الموجودة)
    'status' => '',
]);
$total = $order_repo->countByVendor($vendor_id);
$pages = (int) ceil($total / $limit);

$status_labels = [
    'pending'   => ['label' => __('قيد التنفيذ', 'vmp'), 'class' => 'vmp-status-pending'],
    'completed' => ['label' => __('مكتمل', 'vmp'), 'class' => 'vmp-status-completed'],
    'cancelled' => ['label' => __('ملغي', 'vmp'), 'class' => 'vmp-status-cancelled'],
];

$nav_file = VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php';
?>

<div class="vmp-wrap">
    <?php if ( file_exists($nav_file) ) : ?>
        <?php include $nav_file; ?>
    <?php else : ?>
        <div class="vmp-notice vmp-notice-warning"><?php _e('ملف التنقل غير موجود.', 'vmp'); ?></div>
    <?php endif; ?>

    <div class="vmp-card">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php _e('الطلبات', 'vmp'); ?> (<?php echo (int) $total; ?>)</h2>
        </div>

        <div class="vmp-table-wrap">
            <table class="vmp-table">
                <thead>
                    <tr>
                        <th><?php _e('رقم الطلب', 'vmp'); ?></th>
                        <th><?php _e('التاريخ', 'vmp'); ?></th>
                        <th><?php _e('الحالة', 'vmp'); ?></th>
                        <th><?php _e('الإجمالي', 'vmp'); ?></th>
                        <th><?php _e('أرباحك', 'vmp'); ?></th>
                        <th><?php _e('تفاصيل', 'vmp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty($orders) ): ?>
                        <tr>
                            <td colspan="6">
                                <div class="vmp-empty">
                                    <div class="vmp-empty-icon">🛒</div>
                                    <h3><?php _e('لا توجد طلبات', 'vmp'); ?></h3>
                                    <p><?php _e('لم تتلقَ أي طلبات بعد.', 'vmp'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $orders as $o ): 
                            $badge = $status_labels[$o->status] ?? ['label' => $o->status, 'class' => ''];
                        ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($o->order_id); ?></strong></td>
                                <td><?php echo date_i18n('Y-m-d H:i', strtotime($o->created_at)); ?></td>
                                <td><span class="vmp-badge-status <?php echo esc_attr($badge['class']); ?>"><?php echo esc_html($badge['label']); ?></span></td>
                                <td><?php echo function_exists('wc_price') ? wc_price($o->total) : esc_html($o->total); ?></td>
                                <td><strong style="color:var(--vmp-success);"><?php echo function_exists('wc_price') ? wc_price($o->vendor_earnings) : esc_html($o->vendor_earnings); ?></strong></td>
                                <td>
                                    <!-- ✅ Modal بدلاً من alert() -->
                                    <button type="button" class="vmp-btn vmp-btn-outline vmp-btn-sm vmp-order-details-btn" data-vendor-order-id="<?php echo esc_attr($o->id); ?>" data-order-number="<?php echo esc_attr($o->order_id); ?>">
                                        <?php _e('التفاصيل', 'vmp'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ( $pages > 1 ): ?>
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

<!-- ✅ Modal لعرض تفاصيل الطلب -->
<div id="vmp-order-modal" class="vmp-modal" hidden>
    <div class="vmp-modal-overlay" data-close-modal></div>
    <div class="vmp-modal-content">
        <div class="vmp-modal-header">
            <h3><?php _e('تفاصيل الطلب', 'vmp'); ?> <span id="vmp-order-modal-id"></span></h3>
            <button type="button" class="vmp-modal-close" data-close-modal>&times;</button>
        </div>
        <div class="vmp-modal-body" id="vmp-order-modal-body">
            <p><?php _e('جاري تحميل التفاصيل...', 'vmp'); ?></p>
        </div>
    </div>
</div>




