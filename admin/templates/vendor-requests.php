<?php
/**
 * Admin template for vendor requests management
 */

defined('ABSPATH') || exit;

if (!current_user_can('manage_options') && !current_user_can('vmp_manage_vendors') && !current_user_can('manage_vmp_requests')) {
    wp_die(__('غير مصرح لك الوصول إلى هذه الصفحة.', 'vmp'), 403);
}

$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
?>
<div class="wrap vmp-vendor-requests-wrap">
    <h1 class="wp-heading-inline"><?php _e('طلبات البائعين', 'vmp'); ?></h1>
    
    <div class="vmp-filters-bar" style="margin: 20px 0; display: flex; gap: 16px; flex-wrap: wrap; align-items: center;">
        <!-- Status Filter -->
        <div class="vmp-filter-group" style="display: flex; gap: 8px; align-items: center;">
            <label for="vmp-filter-status" style="font-weight: 500;"><?php _e('الحالة:', 'vmp'); ?></label>
            <select id="vmp-filter-status" name="status" style="min-width: 160px;">
                <option value="all" <?php selected($status, 'all'); ?>><?php _e('الكل', 'vmp'); ?></option>
                <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('قيد المراجعة', 'vmp'); ?></option>
                <option value="approved" <?php selected($status, 'approved'); ?>><?php _e('مقبولة', 'vmp'); ?></option>
                <option value="rejected" <?php selected($status, 'rejected'); ?>><?php _e('مرفوضة', 'vmp'); ?></option>
            </select>
        </div>
        
        <!-- Search -->
        <form method="get" class="vmp-filter-search" style="flex: 1; max-width: 400px;">
            <input type="hidden" name="page" value="vmp-vendor-requests">
            <?php if ($status !== 'all'): ?>
                <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
            <?php endif; ?>
            <label for="vmp-search" class="screen-reader-text"><?php _e('بحث', 'vmp'); ?></label>
            <input type="search" id="vmp-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('بحث بالاسم، البريد، أو الرابط...', 'vmp'); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
        </form>
        
        <!-- Stats -->
        <div class="vmp-stats" style="display: flex; gap: 16px; font-size: 13px; color: #6b7280;">
            <span><strong><?php echo $stats['total'] ?? 0; ?></strong> <?php _e('إجمالي', 'vmp'); ?></span>
            <span style="color: #f59e0b;"><strong><?php echo $stats['pending'] ?? 0; ?></strong> <?php _e('قيد المراجعة', 'vmp'); ?></span>
            <span style="color: #16a34a;"><strong><?php echo $stats['approved'] ?? 0; ?></strong> <?php _e('مقبولة', 'vmp'); ?></span>
            <span style="color: #dc2626;"><strong><?php echo $stats['rejected'] ?? 0; ?></strong> <?php _e('مرفوضة', 'vmp'); ?></span>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped vmp-requests-table">
        <thead>
            <tr>
                <th scope="col" style="width: 60px;"><?php _e('المعرف', 'vmp'); ?></th>
                <th scope="col"><?php _e('المتجر', 'vmp'); ?></th>
                <th scope="col" style="width: 140px;"><?php _e('المستخدم', 'vmp'); ?></th>
                <th scope="col" style="width: 100px;"><?php _e('الحالة', 'vmp'); ?></th>
                <th scope="col" style="width: 140px;"><?php _e('تاريخ الطلب', 'vmp'); ?></th>
                <th scope="col" style="width: 160px;"><?php _e('الإجراءات', 'vmp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="6" class="vmp-empty-state">
                        <span class="dashicons dashicons-store"></span>
                        <p><?php _e('لا توجد طلبات بائعين', 'vmp'); ?></p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <tr data-request-id="<?php echo esc_attr($request->id); ?>">
                        <td><?php echo esc_html($request->id); ?></td>
                        <td>
                            <strong><?php echo esc_html($request->store_name); ?></strong>
                            <br>
                            <small style="color: #6b7280;"><?php echo esc_html($request->store_slug); ?></small>
                        </td>
                        <td>
                            <?php
                            $req_user = get_userdata($request->user_id);
                            $req_email = $req_user ? $req_user->user_email : ($request->store_email ?? 'N/A');
                            ?>
                            <a href="<?php echo esc_url(get_edit_user_link($request->user_id)); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($req_email); ?>
                            </a>
                            <?php if ($request->user_id): ?>
                                <br><small style="color: #6b7280;">#<?php echo esc_html($request->user_id); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusLabels = [
                                'pending' => ['label' => __('قيد المراجعة', 'vmp'), 'class' => 'vmp-status-pending'],
                                'approved' => ['label' => __('مقبولة', 'vmp'), 'class' => 'vmp-status-approved'],
                                'rejected' => ['label' => __('مرفوضة', 'vmp'), 'class' => 'vmp-status-rejected'],
                            ];
                            $st = $statusLabels[$request->status] ?? ['label' => $request->status, 'class' => ''];
                            ?>
                            <span class="vmp-status-badge <?php echo esc_attr($st['class']); ?>">
                                <?php echo esc_html($st['label']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($request->created_at))); ?></td>
                        <td>
                            <div class="vmp-actions-cell">
                                <button type="button" class="button button-small vmp-btn-view" data-request-id="<?php echo esc_attr($request->id); ?>">
                                    <span class="dashicons dashicons-visibility"></span> <?php _e('عرض', 'vmp'); ?>
                                </button>
                                <?php if ($request->status === 'pending'): ?>
                                    <button type="button" class="button button-small vmp-btn-approve" data-request-id="<?php echo esc_attr($request->id); ?>">
                                        <span class="dashicons dashicons-yes-alt"></span> <?php _e('موافقة', 'vmp'); ?>
                                    </button>
                                    <button type="button" class="button button-small vmp-btn-reject" data-request-id="<?php echo esc_attr($request->id); ?>">
                                        <span class="dashicons dashicons-no-alt"></span> <?php _e('رفض', 'vmp'); ?>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="button button-small vmp-btn-delete" data-request-id="<?php echo esc_attr($request->id); ?>">
                                    <span class="dashicons dashicons-trash"></span> <?php _e('حذف', 'vmp'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($totalPages > 1): ?>
        <nav class="vmp-pagination" aria-label="<?php esc_attr_e('التنقل بين الصفحات', 'vmp'); ?>">
            <?php
            $baseUrl = add_query_arg([
                'paged' => '%#%',
            ]);
            if ($status !== 'all') $baseUrl = add_query_arg('status', $status, $baseUrl);
            if ($search) $baseUrl = add_query_arg('s', $search, $baseUrl);
            
            echo paginate_links([
                'base'      => $baseUrl,
                'format'    => '',
                'current'   => $paged,
                'total'     => $totalPages,
                'prev_text' => '&laquo; ' . __('السابق', 'vmp'),
                'next_text' => __('التالي', 'vmp') . ' &raquo;',
                'type'      => 'list',
                'add_args'  => false,
            ]);
            ?>
        </nav>
    <?php endif; ?>
</div>

<!-- View Modal -->
<div class="vmp-modal" id="vmp-request-modal" hidden>
    <div class="vmp-modal-overlay"></div>
    <div class="vmp-modal-content">
        <div class="vmp-modal-header">
            <h2><?php _e('تفاصيل طلب البائع', 'vmp'); ?></h2>
            <button type="button" class="vmp-modal-close" aria-label="<?php esc_attr_e('إغلاق', 'vmp'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="vmp-modal-body" id="vmp-modal-body">
            <div class="vmp-loading"><?php _e('جاري التحميل...', 'vmp'); ?></div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="vmp-modal" id="vmp-reject-modal" hidden>
    <div class="vmp-modal-overlay"></div>
    <div class="vmp-modal-content vmp-modal-small">
        <div class="vmp-modal-header">
            <h2><?php _e('رفض طلب البائع', 'vmp'); ?></h2>
            <button type="button" class="vmp-modal-close" aria-label="<?php esc_attr_e('إغلاق', 'vmp'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="vmp-modal-body">
            <p><?php _e('يرجى إدخال سبب الرفض:', 'vmp'); ?></p>
            <textarea id="vmp-reject-reason" placeholder="<?php esc_attr_e('اكتب سبب الرفض هنا...', 'vmp'); ?>"></textarea>
        </div>
        <div class="vmp-modal-footer">
            <button type="button" class="button vmp-modal-close-btn"><?php _e('إلغاء', 'vmp'); ?></button>
            <button type="button" class="button button-primary vmp-btn-confirm-reject"><?php _e('تأكيد الرفض', 'vmp'); ?></button>
        </div>
    </div>
</div>