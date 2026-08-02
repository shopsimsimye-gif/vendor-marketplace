<?php
if ( !defined('ABSPATH') ) { exit; }

$vendor_id = vmp_get_current_vendor_id();

global $wpdb;
$paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit  = 15;
$offset = ($paged - 1) * $limit;

$table = $wpdb->prefix . 'vmp_vendor_orders';
$sql = "SELECT * FROM {$table} WHERE vendor_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d";
$orders = $wpdb->get_results($wpdb->prepare($sql, $vendor_id, $limit, $offset));

$total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d", $vendor_id));
$pages = ceil($total / $limit);

$status_labels = [
    'pending'   => ['label' => __('قيد التنفيذ', 'vmp'), 'class' => 'vmp-status-pending'],
    'completed' => ['label' => __('مكتمل', 'vmp'), 'class' => 'vmp-status-completed'],
    'cancelled' => ['label' => __('ملغي', 'vmp'), 'class' => 'vmp-status-cancelled'],
];
?>

<div class="vmp-wrap">
    <?php include 'partials/vendor-nav.php'; ?>

    <div class="vmp-card">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php _e('الطلبات', 'vmp'); ?> (<?php echo $total; ?>)</h2>
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
                                <td><span class="vmp-badge-status <?php echo $badge['class']; ?>"><?php echo esc_html($badge['label']); ?></span></td>
                                <td><?php echo wc_price($o->total); ?></td>
                                <td><strong style="color:var(--vmp-success);"><?php echo wc_price($o->vendor_earnings); ?></strong></td>
                                <td>
                                    <!-- Here we can add a link to view order details or an AJAX modal -->
                                    <button class="vmp-btn vmp-btn-outline vmp-btn-sm" onclick="alert('سيعرض تفاصيل الطلب (المنتجات، بيانات الشحن إن وجدت). يمكنك إضافتها لاحقاً كـ Modal.')"><?php _e('التفاصيل', 'vmp'); ?></button>
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
