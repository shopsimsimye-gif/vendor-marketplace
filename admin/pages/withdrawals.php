<?php
if ( !defined('ABSPATH') ) { exit; }

$paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit  = 20;
$offset = ($paged - 1) * $limit;
$status = sanitize_text_field($_GET['vmp_status'] ?? 'pending');

global $wpdb;
$table = $wpdb->prefix . 'vmp_withdrawals';

$where_clause = $status ? $wpdb->prepare("WHERE w.status = %s", $status) : "";
$sql = "
    SELECT w.*, v.store_name, v.balance as current_balance 
    FROM {$table} w
    JOIN {$wpdb->prefix}vmp_vendors v ON w.vendor_id = v.id
    {$where_clause}
    ORDER BY w.created_at DESC
    LIMIT %d OFFSET %d
";
$withdrawals = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));

$total_sql = "SELECT COUNT(*) FROM {$table} w {$where_clause}";
$total     = (int) $wpdb->get_var($total_sql);
$pages     = ceil($total / $limit);

$status_labels = [
    'pending'   => ['label' => __('قيد المراجعة', 'vmp'), 'class' => 'vmp-status-pending'],
    'approved'  => ['label' => __('مقبول (تم التحويل)', 'vmp'), 'class' => 'vmp-status-approved'],
    'rejected'  => ['label' => __('مرفوض', 'vmp'), 'class' => 'vmp-status-rejected'],
];
?>

<div class="wrap vmp-admin-wrap">
    <div class="vmp-admin-header">
        <h1><?php _e('طلبات سحب الأرباح', 'vmp'); ?></h1>
    </div>

    <ul class="subsubsub">
        <li class="pending"><a href="admin.php?page=vmp-withdrawals&vmp_status=pending" class="<?php echo $status === 'pending' ? 'current' : ''; ?>"><?php _e('قيد المراجعة', 'vmp'); ?></a> |</li>
        <li class="approved"><a href="admin.php?page=vmp-withdrawals&vmp_status=approved" class="<?php echo $status === 'approved' ? 'current' : ''; ?>"><?php _e('مقبول', 'vmp'); ?></a> |</li>
        <li class="rejected"><a href="admin.php?page=vmp-withdrawals&vmp_status=rejected" class="<?php echo $status === 'rejected' ? 'current' : ''; ?>"><?php _e('مرفوض', 'vmp'); ?></a> |</li>
        <li class="all"><a href="admin.php?page=vmp-withdrawals&vmp_status=" class="<?php echo empty($status) ? 'current' : ''; ?>"><?php _e('الكل', 'vmp'); ?></a></li>
    </ul>

    <div class="vmp-admin-card" style="margin-top: 15px;">
        <div class="vmp-admin-card-body" style="padding: 0;">
            <table class="vmp-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('رقم الطلب', 'vmp'); ?></th>
                        <th><?php _e('البائع', 'vmp'); ?></th>
                        <th><?php _e('طريقة السحب', 'vmp'); ?></th>
                        <th><?php _e('تفاصيل الدفع', 'vmp'); ?></th>
                        <th><?php _e('المبلغ', 'vmp'); ?></th>
                        <th><?php _e('الحالة', 'vmp'); ?></th>
                        <th><?php _e('التاريخ', 'vmp'); ?></th>
                        <th><?php _e('إجراءات', 'vmp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty($withdrawals) ): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 20px;"><?php _e('لا توجد طلبات سحب.', 'vmp'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ( $withdrawals as $w ): 
                            $badge = $status_labels[$w->status] ?? ['label' => $w->status, 'class' => ''];
                        ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($w->id); ?></strong></td>
                                <td>
                                    <?php echo esc_html($w->store_name); ?><br>
                                    <small><?php _e('الرصيد الحالي:', 'vmp'); ?> <?php echo wc_price($w->current_balance); ?></small>
                                </td>
                                <td><?php echo esc_html($w->payment_method); ?></td>
                                <td><?php echo nl2br(esc_html($w->payment_details)); ?></td>
                                <td><strong style="color:var(--vmp-success);"><?php echo wc_price($w->amount); ?></strong></td>
                                <td><span class="vmp-status <?php echo $badge['class']; ?>"><?php echo esc_html($badge['label']); ?></span></td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($w->created_at)); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ( $w->status === 'pending' ): ?>
                                            <button class="button button-primary vmp-action-btn" data-action="vmp_admin_approve_withdrawal" data-id="<?php echo $w->id; ?>" data-confirm="<?php _e('هل قمت بتحويل المبلغ للبائع متأكد من الموافقة؟', 'vmp'); ?>"><?php _e('موافقة', 'vmp'); ?></button>
                                            <button class="button vmp-action-btn" data-action="vmp_admin_reject_withdrawal" data-id="<?php echo $w->id; ?>" data-confirm="<?php _e('سيتم إعادة المبلغ لرصيد البائع، متأكد من الرفض؟', 'vmp'); ?>"><?php _e('رفض', 'vmp'); ?></button>
                                        <?php endif; ?>
                                    </div>
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
