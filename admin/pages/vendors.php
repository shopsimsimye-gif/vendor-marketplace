<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من الصلاحية ──
if (!current_user_can('vmp_manage_vendors')) {
    wp_die(__('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'vmp'));
}

// ── استخدام الحاوية ──
$container = \VMP\Core\Container::getInstance();
$vendor_repo = $container->make(\VMP\Repositories\VendorRepository::class);

// ── معالجة الإجراءات (موافقة، رفض، حذف) ──
if (isset($_GET['action']) && isset($_GET['vendor_id'])) {
    $vendor_id = (int) $_GET['vendor_id'];
    $action = sanitize_text_field($_GET['action']);
    $nonce = $_GET['_wpnonce'] ?? '';

    if (wp_verify_nonce($nonce, 'vmp_vendor_action_' . $vendor_id)) {
        $vendor = $vendor_repo->find($vendor_id);

        if ($vendor) {
            if ($action === 'approve') {
                $result = $vendor_repo->approve($vendor_id);
                if ($result) {
                    // إضافة دور البائع للمستخدم
                    $user = new \WP_User($vendor->user_id);
                    $user->add_role('vmp_vendor');
                    update_user_meta($vendor->user_id, 'vmp_vendor_status', 'approved');

                    // تسجيل الحدث
                    if (function_exists('vmp_log_success')) {
                        vmp_log_success(
                            sprintf(__('تمت الموافقة على البائع %s', 'vmp'), $vendor->store_name),
                            ['vendor_id' => $vendor_id, 'user_id' => $vendor->user_id],
                            'Vendor'
                        );
                    }

                    $message = __('تمت الموافقة على البائع بنجاح.', 'vmp');
                    $type = 'success';
                } else {
                    $message = __('حدث خطأ أثناء الموافقة.', 'vmp');
                    $type = 'error';
                }
            } elseif ($action === 'reject') {
                $reason = sanitize_textarea_field($_GET['reason'] ?? '');
                $result = $vendor_repo->reject($vendor_id, $reason);
                if ($result) {
                    if (function_exists('vmp_log_warning')) {
                        vmp_log_warning(
                            sprintf(__('تم رفض البائع %s', 'vmp'), $vendor->store_name),
                            ['vendor_id' => $vendor_id, 'reason' => $reason],
                            'Vendor'
                        );
                    }
                    $message = __('تم رفض البائع بنجاح.', 'vmp');
                    $type = 'success';
                } else {
                    $message = __('حدث خطأ أثناء الرفض.', 'vmp');
                    $type = 'error';
                }
            } elseif ($action === 'delete') {
                $result = $vendor_repo->delete($vendor_id);
                if ($result) {
                    if (function_exists('vmp_log_warning')) {
                        vmp_log_warning(
                            sprintf(__('تم حذف البائع %s', 'vmp'), $vendor->store_name),
                            ['vendor_id' => $vendor_id],
                            'Vendor'
                        );
                    }
                    $message = __('تم حذف البائع بنجاح.', 'vmp');
                    $type = 'success';
                } else {
                    $message = __('حدث خطأ أثناء الحذف.', 'vmp');
                    $type = 'error';
                }
            }
        }
    }
}

// ── معالجة البحث والتصفية ──
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit = 20;
$offset = ($paged - 1) * $limit;

// ── جلب البائعين (مباشر بدون تأخير كاش) ──
$args = [
    'limit' => $limit,
    'offset' => $offset,
    'order_by' => 'created_at',
    'order' => 'DESC',
    'with_stats' => true,
];

if (!empty($status)) {
    $args['status'] = $status;
}

if (!empty($search)) {
    $args['search'] = $search;
}

$vendors = $vendor_repo->getAll($args);
$total = $vendor_repo->getCount($status);
$pages = ceil($total / $limit);

// ── إحصائيات سريعة وطلبات الانضمام ──
$quick_stats = $vendor_repo->getQuickStats();
$req_repo = new \VMP\Repositories\VendorRequestRepository();
$pending_requests_count = $req_repo->getCount('pending');
?>

<div class="wrap vmp-admin-wrap">
    <!-- رأس الصفحة -->
    <div class="vmp-admin-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="margin: 0;"><?php _e('إدارة البائعين', 'vmp'); ?></h1>
            <p class="vmp-admin-subtitle" style="margin: 4px 0 0; color: #64748b;"><?php _e('إدارة جميع البائعين المسجلين في منصتك.', 'vmp'); ?></p>
        </div>
        <div class="vmp-admin-header-actions" style="display: flex; align-items: center; gap: 12px;">
            <a href="admin.php?page=vmp-vendor-requests" class="button button-primary" style="background: #2563eb; border-color: #2563eb; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                <span class="dashicons dashicons-id" style="font-size: 18px; width: 18px; height: 18px;"></span>
                <?php _e('طلبات البائعين', 'vmp'); ?>
                <?php if ($pending_requests_count > 0) : ?>
                    <span style="background: #ef4444; color: #fff; border-radius: 10px; padding: 2px 8px; font-size: 11px; margin-right: 4px;"><?php echo (int) $pending_requests_count; ?></span>
                <?php endif; ?>
            </a>
            <span class="vmp-admin-date" style="font-size: 13px; color: #64748b;"><?php echo date_i18n('l, j F Y'); ?></span>
        </div>
    </div>

    <?php if ($pending_requests_count > 0) : ?>
        <!-- شريط تنبيه للطلبات المعلقة -->
        <div class="vmp-admin-card" style="background: #fff8e6; border: 1px solid #fef3c7; border-right: 4px solid #f59e0b; margin-bottom: 20px; padding: 14px 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="dashicons dashicons-clock" style="font-size: 26px; width: 26px; height: 26px; color: #d97706;"></span>
                    <div>
                        <strong style="font-size: 15px; color: #92400e; display: block;">
                            <?php printf(esc_html__('يوجد لديك %d طلب(ات) انضمام كبائع جديد قيد المراجعة!', 'vmp'), $pending_requests_count); ?>
                        </strong>
                        <span style="font-size: 13px; color: #b45309;">
                            <?php esc_html_e('يمكنك مراجعة كافة الطلبات الجديدة المرفوعة والموافقة عليها أو رفضها.', 'vmp'); ?>
                        </span>
                    </div>
                </div>
                <a href="admin.php?page=vmp-vendor-requests" class="button button-primary" style="background: #d97706; border-color: #b45309; font-weight: 600; padding: 6px 16px;">
                    <?php esc_html_e('عرض طلبات البائعين', 'vmp'); ?> &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- إحصائيات سريعة -->
    <div class="vmp-admin-stats" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); margin-bottom: 24px;">
        <div class="vmp-admin-stat-card" style="padding: 12px 16px;">
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('إجمالي البائعين', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value" style="font-size: 20px;"><?php echo (int) $quick_stats['total']; ?></span>
            </div>
        </div>
        <div class="vmp-admin-stat-card" style="padding: 12px 16px; border-color: #10b981;">
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('نشطون', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value" style="font-size: 20px; color:#10b981;"><?php echo (int) $quick_stats['active']; ?></span>
            </div>
        </div>
        <a href="admin.php?page=vmp-vendor-requests" class="vmp-admin-stat-card" style="padding: 12px 16px; border-color: #f59e0b; text-decoration: none; display: block; transition: transform 0.2s ease;">
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label" style="color: #64748b;"><?php _e('طلبات قيد المراجعة', 'vmp'); ?> &rarr;</span>
                <span class="vmp-admin-stat-value" style="font-size: 20px; color:#f59e0b; font-weight: bold;"><?php echo (int) $pending_requests_count; ?></span>
            </div>
        </a>
        <div class="vmp-admin-stat-card" style="padding: 12px 16px; border-color: #6366f1;">
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('إجمالي الرصيد', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value" style="font-size: 20px;"><?php echo wc_price($quick_stats['total_balance'] ?? 0); ?></span>
            </div>
        </div>
    </div>

    <!-- أدوات البحث والتصفية -->
    <div class="vmp-admin-card" style="margin-bottom: 20px;">
        <div class="vmp-admin-card-body">
            <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <input type="hidden" name="page" value="vmp-vendors">

                <div style="flex: 1; min-width: 200px;">
                    <input type="text" name="s" class="vmp-admin-input" style="width:100%; max-width:100%;" 
                           placeholder="<?php _e('ابحث باسم المتجر...', 'vmp'); ?>" 
                           value="<?php echo esc_attr($search); ?>">
                </div>

                <div>
                    <select name="status" class="vmp-admin-select">
                        <option value=""><?php _e('جميع الحالات', 'vmp'); ?></option>
                        <option value="approved" <?php selected($status, 'approved'); ?>><?php _e('نشط', 'vmp'); ?></option>
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('قيد المراجعة', 'vmp'); ?></option>
                        <option value="rejected" <?php selected($status, 'rejected'); ?>><?php _e('مرفوض', 'vmp'); ?></option>
                    </select>
                </div>

                <button type="submit" class="button button-primary">
                    <?php _e('بحث', 'vmp'); ?>
                </button>
                <a href="admin.php?page=vmp-vendors" class="button button-secondary">
                    <?php _e('إعادة تعيين', 'vmp'); ?>
                </a>
            </form>
        </div>
    </div>

    <!-- جدول البائعين -->
    <div class="vmp-admin-card">
        <div class="vmp-admin-card-body" style="padding: 0;">
            <?php if (empty($vendors)) : ?>
                <div class="vmp-admin-empty-state">
                    <span class="dashicons dashicons-store"></span>
                    <p><?php _e('لا يوجد بائعون مطابقون للبحث.', 'vmp'); ?></p>
                    <?php if ($pending_requests_count > 0) : ?>
                        <p style="font-size:13px; color:#d97706; font-weight: 500; margin-top: 8px;">
                            <?php printf(__('تنبيه: يوجد %d طلب(ات) انضمام كبائع قيد المراجعة في صفحة طلبات البائعين.', 'vmp'), $pending_requests_count); ?>
                        </p>
                        <a href="admin.php?page=vmp-vendor-requests" class="button button-primary" style="margin-top: 10px; background: #d97706; border-color: #b45309;">
                            <?php _e('عرض طلبات البائعين المعلقة', 'vmp'); ?> (<?php echo (int) $pending_requests_count; ?>) &rarr;
                        </a>
                    <?php elseif (!empty($search) || !empty($status)) : ?>
                        <p style="font-size:13px; color:#94a3b8;">
                            <?php _e('حاول تعديل معايير البحث.', 'vmp'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <table class="vmp-admin-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="cb-select-all">
                            </th>
                            <th><?php _e('المتجر', 'vmp'); ?></th>
                            <th><?php _e('البائع', 'vmp'); ?></th>
                            <th><?php _e('الرصيد', 'vmp'); ?></th>
                            <th><?php _e('المنتجات', 'vmp'); ?></th>
                            <th><?php _e('الطلبات', 'vmp'); ?></th>
                            <th><?php _e('الحالة', 'vmp'); ?></th>
                            <th><?php _e('تاريخ التسجيل', 'vmp'); ?></th>
                            <th><?php _e('إجراءات', 'vmp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $vendor) : 
                            $user = get_userdata($vendor->user_id);
                            $stats = $vendor->stats ?? ['total_products' => 0, 'total_orders' => 0];
                            $status_class = match($vendor->status) {
                                'approved' => 'vmp-status-active',
                                'pending' => 'vmp-status-pending',
                                'rejected' => 'vmp-status-rejected',
                                default => '',
                            };
                            $status_label = match($vendor->status) {
                                'approved' => __('نشط', 'vmp'),
                                'pending' => __('قيد المراجعة', 'vmp'),
                                'rejected' => __('مرفوض', 'vmp'),
                                default => $vendor->status,
                            };
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="vmp-row-cb" value="<?php echo (int) $vendor->id; ?>">
                                </td>
                                <td>
                                    <strong><?php echo esc_html($vendor->store_name); ?></strong>
                                    <br>
                                    <small style="color: #64748b;">
                                        <a href="<?php echo home_url('/store/' . $vendor->store_slug); ?>" target="_blank">
                                            /<?php echo esc_html($vendor->store_slug); ?>
                                        </a>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($user) : ?>
                                        <a href="<?php echo get_edit_user_link($vendor->user_id); ?>" target="_blank">
                                            <?php echo esc_html($user->display_name); ?>
                                        </a>
                                        <br>
                                        <small style="color: #64748b;"><?php echo esc_html($user->user_email); ?></small>
                                    <?php else : ?>
                                        <span style="color:#ef4444;"><?php _e('مستخدم محذوف', 'vmp'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo wc_price($vendor->balance ?? 0); ?></strong>
                                </td>
                                <td><?php echo (int) ($stats['total_products'] ?? 0); ?></td>
                                <td><?php echo (int) ($stats['total_orders'] ?? 0); ?></td>
                                <td>
                                    <span class="vmp-status <?php echo $status_class; ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td style="direction:ltr; text-align:left; font-size:12px;">
                                    <?php echo date_i18n('Y-m-d', strtotime($vendor->created_at)); ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:4px; flex-wrap:wrap; justify-content:flex-end;">
                                        <?php if ($vendor->status === 'pending') : ?>
                                            <a href="<?php echo wp_nonce_url(add_query_arg(['page' => 'vmp-vendors', 'action' => 'approve', 'vendor_id' => $vendor->id]), 'vmp_vendor_action_' . $vendor->id); ?>" 
                                               class="button button-small button-primary">
                                                <?php _e('موافقة', 'vmp'); ?>
                                            </a>
                                            <a href="#" class="button button-small vmp-reject-vendor" 
                                               data-vendor-id="<?php echo (int) $vendor->id; ?>"
                                               data-nonce="<?php echo wp_create_nonce('vmp_vendor_action_' . $vendor->id); ?>">
                                                <?php _e('رفض', 'vmp'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($vendor->status === 'approved') : ?>
                                            <a href="<?php echo home_url('/store/' . $vendor->store_slug); ?>" 
                                               target="_blank" class="button button-small">
                                                <?php _e('عرض المتجر', 'vmp'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo wp_nonce_url(add_query_arg(['page' => 'vmp-vendors', 'action' => 'delete', 'vendor_id' => $vendor->id]), 'vmp_vendor_action_' . $vendor->id); ?>" 
                                           class="button button-small vmp-delete-vendor" 
                                           onclick="return confirm('<?php _e('هل أنت متأكد من حذف هذا البائع نهائياً؟', 'vmp'); ?>')">
                                            <?php _e('حذف', 'vmp'); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- الترقيم -->
        <?php if ($pages > 1) : ?>
            <div class="vmp-admin-card-body" style="border-top: 1px solid #e2e8f0;">
                <div class="vmp-pagination">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $paged,
                        'total' => $pages,
                        'prev_text' => '‹',
                        'next_text' => '›',
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ✅ مودال رفض البائع -->
<div class="vmp-modal-overlay" id="vmp-reject-modal" style="display:none;">
    <div class="vmp-modal" style="max-width: 500px;">
        <div class="vmp-modal-header">
            <h2><?php _e('رفض البائع', 'vmp'); ?></h2>
            <button class="vmp-modal-close">&times;</button>
        </div>
        <div class="vmp-modal-body">
            <form id="vmp-reject-form">
                <input type="hidden" name="vendor_id" id="vmp-reject-vendor-id" value="">
                <input type="hidden" name="nonce" id="vmp-reject-nonce" value="">

                <div class="vmp-field-group">
                    <label><?php _e('سبب الرفض', 'vmp'); ?></label>
                    <textarea name="reason" id="vmp-reject-reason" class="vmp-field" rows="4" 
                              placeholder="<?php _e('أدخل سبب الرفض...', 'vmp'); ?>"></textarea>
                </div>

                <div class="vmp-actions" style="margin-top: 16px;">
                    <button type="button" class="vmp-btn vmp-btn-secondary vmp-modal-cancel"><?php _e('إلغاء', 'vmp'); ?></button>
                    <button type="submit" class="vmp-btn vmp-btn-primary"><?php _e('تأكيد الرفض', 'vmp'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // ── تحديد الكل ──
    $('#cb-select-all').on('change', function() {
        $('.vmp-row-cb').prop('checked', $(this).prop('checked'));
    });

    // ── فتح مودال الرفض ──
    $(document).on('click', '.vmp-reject-vendor', function(e) {
        e.preventDefault();
        var vendorId = $(this).data('vendor-id');
        var nonce = $(this).data('nonce');

        $('#vmp-reject-vendor-id').val(vendorId);
        $('#vmp-reject-nonce').val(nonce);
        $('#vmp-reject-modal').show();
    });

    // ── إغلاق المودال ──
    $(document).on('click', '.vmp-modal-close, .vmp-modal-cancel', function() {
        $('#vmp-reject-modal').hide();
    });

    $(document).on('click', '#vmp-reject-modal', function(e) {
        if ($(e.target).is(this)) {
            $(this).hide();
        }
    });

    // ── إرسال رفض البائع ──
    $('#vmp-reject-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $(this).find('button[type="submit"]');
        var $notice = $('#vmp-admin-notice');

        $btn.prop('disabled', true).text('<?php _e('جاري...', 'vmp'); ?>');

        $.post(ajaxurl, {
            action: 'vmp_admin_reject_vendor',
            nonce: $('#vmp-reject-nonce').val(),
            vendor_id: $('#vmp-reject-vendor-id').val(),
            reason: $('#vmp-reject-reason').val()
        }, function(response) {
            $btn.prop('disabled', false).text('<?php _e('تأكيد الرفض', 'vmp'); ?>');
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data.message || '<?php _e('حدث خطأ', 'vmp'); ?>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('<?php _e('تأكيد الرفض', 'vmp'); ?>');
            alert('<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>');
        });
    });
});
</script>

<style>
/* أنماط إضافية للمودال */
.vmp-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.vmp-modal-overlay.show { display: flex; }
.vmp-modal {
    background: #fff;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.vmp-modal-header {
    padding: 16px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.vmp-modal-header h2 { margin: 0; font-size: 18px; }
.vmp-modal-close {
    background: none; border: none; font-size: 24px; cursor: pointer;
}
.vmp-modal-body { padding: 24px; }
.vmp-field-group { margin-bottom: 16px; }
.vmp-field-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 4px;
}
.vmp-field {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
}
.vmp-field:focus {
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.vmp-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.vmp-btn {
    padding: 10px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
}
.vmp-btn-primary { background: #6366f1; color: #fff; }
.vmp-btn-secondary { background: #f1f5f9; color: #475569; }
</style>