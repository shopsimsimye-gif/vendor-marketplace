<?php
if ( !defined('ABSPATH') ) { exit; }

$vendor_id = vmp_get_current_vendor_id();
$vendor    = vmp_get_vendor($vendor_id);

if ( !$vendor ) {
    echo '<div class="vmp-wrap"><div class="vmp-notice vmp-notice-error">' . esc_html__( 'البائع غير موجود أو لا تملك صلاحية للوصول إلى هذه الصفحة.', 'vmp' ) . '</div></div>';
    return;
}

global $wpdb;
$paged  = max(1, (int) get_query_var('paged', 1));
$limit  = 15;
$offset = ($paged - 1) * $limit;

$table = $wpdb->prefix . 'vmp_withdrawals';
$sql = "SELECT * FROM {$table} WHERE vendor_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d";
$withdrawals = $wpdb->get_results($wpdb->prepare($sql, $vendor_id, $limit, $offset));

$total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d", $vendor_id));
$pages = ceil($total / $limit);

$status_labels = [
    'pending'   => ['label' => __('قيد المراجعة', 'vmp'), 'class' => 'vmp-status-pending'],
    'approved'  => ['label' => __('مكتمل', 'vmp'), 'class' => 'vmp-status-completed'],
    'rejected'  => ['label' => __('مرفوض', 'vmp'), 'class' => 'vmp-status-rejected'],
];

$settings = get_option('vmp_settings', []);
$min_withdrawal = (float) ($settings['finance']['min_withdrawal'] ?? 100);
$can_withdraw = $vendor->balance >= $min_withdrawal;
$bank_hint_js = esc_js(__('أدخل اسم البنك، رقم الحساب، والآيبان.', 'vmp'));
$paypal_hint_js = esc_js(__('أدخل البريد الإلكتروني لحساب باي بال.', 'vmp'));
?>

<div class="vmp-wrap">
    <?php include 'partials/vendor-nav.php'; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
        
        <!-- بطاقة الرصيد وطلب السحب -->
        <div>
            <div class="vmp-balance-card">
                <div>
                    <div class="vmp-balance-label"><?php _e('الرصيد المتاح للسحب', 'vmp'); ?></div>
                    <div class="vmp-balance-amount"><?php echo wc_price($vendor->balance); ?></div>
                </div>
                <div style="font-size: 32px; opacity: 0.5;">💰</div>
            </div>

            <div class="vmp-card">
                <div class="vmp-card-header">
                    <h2 class="vmp-card-title"><?php _e('طلب سحب جديد', 'vmp'); ?></h2>
                </div>
                
                <?php if ( $can_withdraw ): ?>
                    <form id="vmp-withdraw-form" class="vmp-ajax-form vmp-reset-on-success" data-action="vmp_vendor_request_withdrawal">
                        <div class="vmp-form-group">
                            <label><?php _e('المبلغ المطلوب', 'vmp'); ?> <span class="required">*</span></label>
                            <input type="number" step="0.01" name="amount" class="vmp-input" max="<?php echo esc_attr($vendor->balance); ?>" required>
                            <div class="vmp-input-hint"><?php printf(__('الحد الأدنى: %s', 'vmp'), wc_price($min_withdrawal)); ?></div>
                        </div>

                        <div class="vmp-form-group">
                            <label><?php _e('طريقة السحب', 'vmp'); ?> <span class="required">*</span></label>
                            <select name="payment_method" class="vmp-select" required onchange="document.getElementById('vmp_payment_details_hint').innerText = this.value === 'bank_transfer' ? '<?php echo $bank_hint_js; ?>' : '<?php echo $paypal_hint_js; ?>';">
                                <option value="bank_transfer"><?php _e('تحويل بنكي', 'vmp'); ?></option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>

                        <div class="vmp-form-group">
                            <label><?php _e('بيانات الحساب / الدفع', 'vmp'); ?> <span class="required">*</span></label>
                            <textarea name="payment_details" class="vmp-textarea" required rows="3"></textarea>
                            <div class="vmp-input-hint" id="vmp_payment_details_hint"><?php echo esc_html(__('أدخل اسم البنك، رقم الحساب، والآيبان.', 'vmp')); ?></div>
                        </div>

                        <button type="submit" class="vmp-btn vmp-btn-primary vmp-btn-block">
                            <?php _e('تقديم الطلب', 'vmp'); ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="vmp-notice vmp-notice-warning">
                        <?php printf(__('عذراً، رصيدك الحالي أقل من الحد الأدنى للسحب وهو %s.', 'vmp'), wc_price($min_withdrawal)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- سجل السحوبات -->
        <div class="vmp-card">
            <div class="vmp-card-header">
                <h2 class="vmp-card-title"><?php _e('سجل السحوبات', 'vmp'); ?></h2>
            </div>
            
            <div class="vmp-table-wrap">
                <table class="vmp-table">
                    <thead>
                        <tr>
                            <th><?php _e('رقم', 'vmp'); ?></th>
                            <th><?php _e('المبلغ', 'vmp'); ?></th>
                            <th><?php _e('الطريقة', 'vmp'); ?></th>
                            <th><?php _e('الحالة', 'vmp'); ?></th>
                            <th><?php _e('التاريخ', 'vmp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty($withdrawals) ): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--vmp-text-muted);"><?php _e('لا توجد عمليات سحب سابقة.', 'vmp'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ( $withdrawals as $w ): 
                                $badge = $status_labels[$w->status] ?? ['label' => $w->status, 'class' => ''];
                            ?>
                                <tr>
                                    <td>#<?php echo esc_html($w->id); ?></td>
                                    <td><strong><?php echo wc_price($w->amount); ?></strong></td>
                                    <td><?php echo esc_html($w->payment_method); ?></td>
                                    <td><span class="vmp-badge-status <?php echo $badge['class']; ?>"><?php echo esc_html($badge['label']); ?></span></td>
                                    <td><?php echo date_i18n('Y-m-d', strtotime($w->created_at)); ?></td>
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
</div>

<div class="vmp-loading"><div class="vmp-spinner"></div></div>
