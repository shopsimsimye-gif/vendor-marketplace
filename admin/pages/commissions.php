<?php
if ( !defined('ABSPATH') ) { exit; }

$paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit  = 20;
$offset = ($paged - 1) * $limit;
$status = sanitize_text_field($_GET['vmp_status'] ?? 'pending');

global $wpdb;
$table = $wpdb->prefix . 'vmp_commissions';

$where_clause = $status ? $wpdb->prepare("WHERE c.status = %s", $status) : "";
$sql = "
    SELECT c.*, v.store_name 
    FROM {$table} c
    JOIN {$wpdb->prefix}vmp_vendors v ON c.vendor_id = v.id
    {$where_clause}
    ORDER BY c.created_at DESC
    LIMIT %d OFFSET %d
";
$commissions = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));

$total_sql = "SELECT COUNT(*) FROM {$table} c {$where_clause}";
$total     = (int) $wpdb->get_var($total_sql);
$pages     = ceil($total / $limit);

$status_labels = [
    'pending' => ['label' => __('غير مدفوعة', 'vmp'), 'class' => 'vmp-status-pending'],
    'paid'    => ['label' => __('مدفوعة', 'vmp'), 'class' => 'vmp-status-paid'],
];
?>

<div class="wrap vmp-admin-wrap">
    <div class="vmp-admin-header">
        <h1><?php _e('العمولات المستحقة للموقع', 'vmp'); ?></h1>
    </div>

    <ul class="subsubsub">
        <li class="pending"><a href="admin.php?page=vmp-commissions&vmp_status=pending" class="<?php echo $status === 'pending' ? 'current' : ''; ?>"><?php _e('غير مدفوعة', 'vmp'); ?></a> |</li>
        <li class="paid"><a href="admin.php?page=vmp-commissions&vmp_status=paid" class="<?php echo $status === 'paid' ? 'current' : ''; ?>"><?php _e('مدفوعة', 'vmp'); ?></a> |</li>
        <li class="all"><a href="admin.php?page=vmp-commissions&vmp_status=" class="<?php echo empty($status) ? 'current' : ''; ?>"><?php _e('الكل', 'vmp'); ?></a></li>
    </ul>

    <div class="vmp-admin-card" style="margin-top: 15px;">
        <div class="vmp-admin-card-header">
            <?php if ( $status === 'pending' ): ?>
                <button type="button" class="button button-primary" id="vmp-bulk-pay-btn"><?php _e('دفع العمولات المحددة', 'vmp'); ?></button>
            <?php else: ?>
                <h2><?php _e('سجل العمولات', 'vmp'); ?></h2>
            <?php endif; ?>
        </div>
        <div class="vmp-admin-card-body" style="padding: 0;">
            <table class="vmp-admin-table">
                <thead>
                    <tr>
                        <?php if ( $status === 'pending' ): ?>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="cb-select-all"></th>
                        <?php endif; ?>
                        <th><?php _e('الطلب الأساسي', 'vmp'); ?></th>
                        <th><?php _e('البائع', 'vmp'); ?></th>
                        <th><?php _e('إجمالي الطلب', 'vmp'); ?></th>
                        <th><?php _e('نسبة العمولة', 'vmp'); ?></th>
                        <th><?php _e('مبلغ العمولة', 'vmp'); ?></th>
                        <th><?php _e('الحالة', 'vmp'); ?></th>
                        <th><?php _e('التاريخ', 'vmp'); ?></th>
                        <th><?php _e('إجراءات', 'vmp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty($commissions) ): ?>
                        <tr><td colspan="<?php echo $status === 'pending' ? 9 : 8; ?>" style="text-align: center; padding: 20px;"><?php _e('لا توجد عمولات.', 'vmp'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ( $commissions as $c ): 
                            $badge = $status_labels[$c->status] ?? ['label' => $c->status, 'class' => ''];
                        ?>
                            <tr>
                                <?php if ( $status === 'pending' ): ?>
                                    <td style="text-align: center;"><input type="checkbox" class="vmp-row-cb" value="<?php echo $c->id; ?>"></td>
                                <?php endif; ?>
                                <td><a href="<?php echo get_edit_post_link($c->order_id); ?>" target="_blank"><strong>#<?php echo esc_html($c->order_id); ?></strong></a></td>
                                <td><?php echo esc_html($c->store_name); ?></td>
                                <td><?php echo wc_price($c->amount); ?></td>
                                <td><?php echo esc_html($c->commission_rate); ?>%</td>
                                <td><strong style="color:var(--vmp-admin-primary);"><?php echo wc_price($c->commission_amount); ?></strong></td>
                                <td><span class="vmp-status <?php echo $badge['class']; ?>"><?php echo esc_html($badge['label']); ?></span></td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($c->created_at)); ?></td>
                                <td>
                                    <?php if ( $c->status === 'pending' ): ?>
                                        <button class="button vmp-action-btn" data-action="vmp_admin_pay_commission" data-id="<?php echo $c->id; ?>" data-confirm="<?php _e('تأكيد الدفع؟', 'vmp'); ?>"><?php _e('تحديد كمدفوعة', 'vmp'); ?></button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
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
