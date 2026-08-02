<?php
if ( !defined('ABSPATH') ) { exit; }

$product_repo = new \VMP\Repositories\ProductRepository();

$status = sanitize_text_field($_GET['vmp_status'] ?? '');
$paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit  = 20;
$offset = ($paged - 1) * $limit;

global $wpdb;
$table = $wpdb->prefix . 'vmp_vendor_products';

// الحصول على المنتجات مع بيانات البائع والمنتج الأساسي من ووكومرس
$where_clause = $status ? $wpdb->prepare("WHERE p.status = %s", $status) : "";
$sql = "
    SELECT p.*, v.store_name 
    FROM {$table} p
    JOIN {$wpdb->prefix}vmp_vendors v ON p.vendor_id = v.id
    {$where_clause}
    ORDER BY p.created_at DESC
    LIMIT %d OFFSET %d
";
$products = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));

$total_sql = "SELECT COUNT(*) FROM {$table} p {$where_clause}";
$total     = (int) $wpdb->get_var($total_sql);
$pages     = ceil($total / $limit);

$status_labels = [
    'pending'  => ['label' => __('في انتظار المراجعة', 'vmp'), 'class' => 'vmp-status-pending'],
    'approved' => ['label' => __('مقبول', 'vmp'), 'class' => 'vmp-status-approved'],
    'rejected' => ['label' => __('مرفوض', 'vmp'), 'class' => 'vmp-status-rejected'],
];
?>

<div class="wrap vmp-admin-wrap">
    <div class="vmp-admin-header">
        <h1><?php _e('منتجات البائعين', 'vmp'); ?></h1>
    </div>

    <ul class="subsubsub">
        <li class="all"><a href="admin.php?page=vmp-products" class="<?php echo empty($status) ? 'current' : ''; ?>"><?php _e('الكل', 'vmp'); ?></a> |</li>
        <li class="pending"><a href="admin.php?page=vmp-products&vmp_status=pending" class="<?php echo $status === 'pending' ? 'current' : ''; ?>"><?php _e('في انتظار المراجعة', 'vmp'); ?></a> |</li>
        <li class="approved"><a href="admin.php?page=vmp-products&vmp_status=approved" class="<?php echo $status === 'approved' ? 'current' : ''; ?>"><?php _e('مقبول', 'vmp'); ?></a> |</li>
        <li class="rejected"><a href="admin.php?page=vmp-products&vmp_status=rejected" class="<?php echo $status === 'rejected' ? 'current' : ''; ?>"><?php _e('مرفوض', 'vmp'); ?></a></li>
    </ul>

    <div class="vmp-admin-card" style="margin-top: 15px;">
        <div class="vmp-admin-card-body" style="padding: 0;">
            <table class="vmp-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('المنتج', 'vmp'); ?></th>
                        <th><?php _e('البائع', 'vmp'); ?></th>
                        <th><?php _e('السعر', 'vmp'); ?></th>
                        <th><?php _e('الحالة', 'vmp'); ?></th>
                        <th><?php _e('تاريخ الإضافة', 'vmp'); ?></th>
                        <th><?php _e('إجراءات', 'vmp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty($products) ): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 20px;"><?php _e('لا يوجد منتجات للعرض.', 'vmp'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ( $products as $p ): 
                            $wc_product = wc_get_product($p->product_id);
                            $badge = $status_labels[$p->status] ?? ['label' => $p->status, 'class' => ''];
                        ?>
                            <tr>
                                <td>
                                    <?php if ( $wc_product ): ?>
                                        <a href="<?php echo get_edit_post_link($p->product_id); ?>" target="_blank"><strong><?php echo esc_html($wc_product->get_name()); ?></strong></a>
                                    <?php else: ?>
                                        <span style="color:red;"><?php _e('منتج محذوف', 'vmp'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><a href="admin.php?page=vmp-vendors&vmp_status=all"><?php echo esc_html($p->store_name); ?></a></td>
                                <td><?php echo $wc_product ? $wc_product->get_price_html() : '-'; ?></td>
                                <td><span class="vmp-status <?php echo $badge['class']; ?>"><?php echo esc_html($badge['label']); ?></span></td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($p->created_at)); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ( $p->status === 'pending' ): ?>
                                            <button class="button button-primary vmp-action-btn" data-action="vmp_admin_approve_product" data-id="<?php echo $p->id; ?>" data-confirm="<?php _e('موافقة على المنتج ونشره؟', 'vmp'); ?>"><?php _e('موافقة', 'vmp'); ?></button>
                                            <button class="button vmp-action-btn" data-action="vmp_admin_reject_product" data-id="<?php echo $p->id; ?>" data-confirm="<?php _e('رفض هذا المنتج؟', 'vmp'); ?>"><?php _e('رفض', 'vmp'); ?></button>
                                        <?php endif; ?>
                                        <?php if ( $wc_product ): ?>
                                            <a href="<?php echo get_permalink($p->product_id); ?>" target="_blank" class="button"><?php _e('عرض', 'vmp'); ?></a>
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
