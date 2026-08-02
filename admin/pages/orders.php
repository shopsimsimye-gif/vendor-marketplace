<?php
if ( !defined('ABSPATH') ) { exit; }

$paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit  = 20;
$offset = ($paged - 1) * $limit;

global $wpdb;
$table = $wpdb->prefix . 'vmp_vendor_orders';

// الحصول على الطلبات مع بيانات البائع
$sql = "
    SELECT o.*, v.store_name 
    FROM {$table} o
    JOIN {$wpdb->prefix}vmp_vendors v ON o.vendor_id = v.id
    ORDER BY o.created_at DESC
    LIMIT %d OFFSET %d
";
$orders = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));

$total_sql = "SELECT COUNT(*) FROM {$table}";
$total     = (int) $wpdb->get_var($total_sql);
$pages     = ceil($total / $limit);

$status_labels = [
    'pending'   => ['label' => __('قيد الانتظار', 'vmp'), 'class' => 'vmp-status-pending'],
    'completed' => ['label' => __('مكتمل', 'vmp'), 'class' => 'vmp-status-completed'],
    'cancelled' => ['label' => __('ملغي', 'vmp'), 'class' => 'vmp-status-cancelled'],
];
?>

<div class="wrap vmp-admin-wrap">
    <div class="vmp-admin-header">
        <h1><?php _e('طلبات البائعين', 'vmp'); ?></h1>
    </div>

    <div class="vmp-admin-card">
        <div class="vmp-admin-card-body" style="padding: 0;">
            <table class="vmp-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('الطلب الأساسي', 'vmp'); ?></th>
                        <th><?php _e('البائع', 'vmp'); ?></th>
                        <th><?php _e('الإجمالي', 'vmp'); ?></th>
                        <th><?php _e('أرباح البائع', 'vmp'); ?></th>
                        <th><?php _e('العمولة', 'vmp'); ?></th>
                        <th><?php _e('الحالة', 'vmp'); ?></th>
                        <th><?php _e('التاريخ', 'vmp'); ?></th>
                        <th><?php _e('إجراءات', 'vmp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty($orders) ): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 20px;"><?php _e('لا توجد طلبات للعرض.', 'vmp'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ( $orders as $o ): 
                            $badge = $status_labels[$o->status] ?? ['label' => $o->status, 'class' => ''];
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_post_link($o->order_id); ?>" target="_blank">
                                        <strong>#<?php echo esc_html($o->order_id); ?></strong>
                                    </a>
                                </td>
                                <td><?php echo esc_html($o->store_name); ?></td>
                                <td><?php echo wc_price($o->total); ?></td>
                                <td><span style="color:var(--vmp-success); font-weight:bold;"><?php echo wc_price($o->vendor_earnings); ?></span></td>
                                <td><span style="color:var(--vmp-admin-primary); font-weight:bold;"><?php echo wc_price($o->commission); ?></span></td>
                                <td><span class="vmp-status <?php echo $badge['class']; ?>"><?php echo esc_html($badge['label']); ?></span></td>
                                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($o->created_at)); ?></td>
                                <td>
                                    <button class="button vmp-action-btn" data-action="vmp_admin_get_order_details" data-id="<?php echo $o->id; ?>"><?php _e('التفاصيل', 'vmp'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ( $pages > 1 ): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(_n('%s عنصر', '%s عناصر', $total, 'vmp'), number_format_i18n($total)); ?></span>
                <span class="pagination-links">
                    <?php
                    echo paginate_links([
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'prev_text' => __( '&laquo;', 'vmp' ),
                        'next_text' => __( '&raquo;', 'vmp' ),
                        'total'     => $pages,
                        'current'   => $paged,
                    ]);
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="vmp-admin-loading"><span class="spinner is-active" style="float:none;width:30px;height:30px;"></span></div>
