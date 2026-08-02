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

<script>
jQuery(document).ready(function($) {
    // فتح الـ Modal عند النقر على زر التفاصيل
    $(document).on('click', '.vmp-order-details-btn', function(e) {
        e.preventDefault();
        var vendorOrderId = $(this).data('vendor-order-id');
        var orderNumber = $(this).data('order-number');
        var $modal = $('#vmp-order-modal');
        $('#vmp-order-modal-id').text('#' + orderNumber);
        $('#vmp-order-modal-body').html('<p>' + '<?php echo esc_js(__('جاري تحميل التفاصيل...', 'vmp')); ?>' + '</p>');
        $modal.prop('hidden', false);

        $.post(vmp_public.ajax_url, {
            action: 'vmp_get_order_details',
            nonce: vmp_public.nonce,
            vendor_order_id: vendorOrderId
        }, function(response) {
            if (response.success && response.data) {
                var d = response.data;
                var html = '';
                var v = d.vendor_order || {};
                html += '<p><strong><?php echo esc_js(__('رقم الطلب:', 'vmp')); ?></strong> ' + (d.order_number || v.order_id || '') + '</p>';
                if (d.customer_name) {
                    html += '<p><strong><?php echo esc_js(__('العميل:', 'vmp')); ?></strong> ' + d.customer_name + '</p>';
                }
                if (d.customer_email) {
                    html += '<p><strong><?php echo esc_js(__('البريد:', 'vmp')); ?></strong> ' + d.customer_email + '</p>';
                }
                if (d.order_date) {
                    html += '<p><strong><?php echo esc_js(__('التاريخ:', 'vmp')); ?></strong> ' + d.order_date + '</p>';
                }
                if (v.total) {
                    html += '<p class="vmp-order-modal-total"><strong><?php echo esc_js(__('الإجمالي:', 'vmp')); ?> ' + v.total + '</strong></p>';
                }
                $('#vmp-order-modal-body').html(html || '<p><?php echo esc_js(__('لا توجد تفاصيل إضافية.', 'vmp')); ?></p>');
            } else {
                $('#vmp-order-modal-body').html('<p>' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('تعذر تحميل التفاصيل.', 'vmp')); ?>') + '</p>');
            }
        }).fail(function() {
            $('#vmp-order-modal-body').html('<p><?php echo esc_js(__('حدث خطأ أثناء تحميل التفاصيل.', 'vmp')); ?></p>');
        });
    });

    // إغلاق الـ Modal
    $(document).on('click', '[data-close-modal]', function() {
        $('#vmp-order-modal').prop('hidden', true);
    });
});
</script>

<style>
.vmp-modal { position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center; }
.vmp-modal[hidden] { display: none; }
.vmp-modal-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
.vmp-modal-content { position: relative; background: #fff; border-radius: 12px; max-width: 520px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
.vmp-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--vmp-border, #e2e8f0); }
.vmp-modal-header h3 { margin: 0; font-size: 16px; }
.vmp-modal-close { background: none; border: none; font-size: 24px; line-height: 1; cursor: pointer; color: #64748b; }
.vmp-modal-body { padding: 20px; }
.vmp-order-modal-items { margin: 0 0 12px; padding: 0; list-style: none; }
.vmp-order-modal-items li { padding: 8px 0; border-bottom: 1px solid var(--vmp-border, #e2e8f0); }
</style>
