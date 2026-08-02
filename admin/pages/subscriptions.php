<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من الصلاحية ──
if (!current_user_can('manage_options')) {
    wp_die(__('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'vmp'));
}

// ── جلب جميع الخطط ──
$plan_repo = new \VMP\Repositories\SubscriptionPlanRepository();
$plans = $plan_repo->getAll(false);
?>

<div class="wrap vmp-admin-wrap">
    <h1 class="wp-heading-inline"><?php _e('خطط الاشتراك', 'vmp'); ?></h1>
    <button class="page-title-action vmp-open-modal"><?php _e('إضافة خطة جديدة', 'vmp'); ?></button>
    <hr class="wp-header-end">

    <!-- ✅ استخدام نظام الإشعارات الموحد -->
    <div id="vmp-admin-notice" style="display:none;" class="notice"></div>

    <?php
    // عرض إشعارات المشرف (من option) ليضمن وصول الإشعارات حتى لو فشل البريد
    $vmp_admin_notices = get_option('vmp_admin_notices', []);
    if (!empty($vmp_admin_notices) && is_array($vmp_admin_notices)) : ?>
        <div class="vmp-admin-notices" style="margin:12px 0;">
            <?php foreach (array_slice($vmp_admin_notices, 0, 20) as $an) :
                $type = isset($an['type']) ? esc_attr($an['type']) : 'info';
                $msg  = isset($an['message']) ? esc_html($an['message']) : '';
                $created = isset($an['created_at']) ? esc_html($an['created_at']) : '';
            ?>
                <div class="notice notice-<?php echo $type; ?>" style="margin-bottom:8px; padding:10px;">
                    <p style="margin:0;"><strong><?php echo $msg; ?></strong> <span style="color:#6b7280; font-size:12px;"><?php echo $created; ?></span></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── جدول الخطط ── -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('الاسم', 'vmp'); ?></th>
                <th><?php _e('السعر', 'vmp'); ?></th>
                <th><?php _e('المدة', 'vmp'); ?></th>
                <th><?php _e('العمولة', 'vmp'); ?></th>
                <th><?php _e('الحد الأقصى', 'vmp'); ?></th>
                <th><?php _e('الحالة', 'vmp'); ?></th>
                <th><?php _e('إجراءات', 'vmp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($plans)) : ?>
                <tr><td colspan="7" style="text-align:center;"><?php _e('لا توجد خطط.', 'vmp'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($plans as $plan) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($plan->name); ?></strong></td>
                        <td><?php echo wc_price($plan->price); ?></td>
                        <td><?php echo $plan->billing_period === 'month' ? __('شهري', 'vmp') : __('سنوي', 'vmp'); ?></td>
                        <td><?php echo (float) $plan->commission_rate; ?>%</td>
                        <td><?php echo (int) $plan->max_products === -1 ? __('غير محدود', 'vmp') : (int) $plan->max_products; ?></td>
                        <td>
                            <span class="vmp-badge-status <?php echo $plan->is_active ? 'vmp-status-approved' : 'vmp-status-rejected'; ?>">
                                <?php echo $plan->is_active ? __('مفعل', 'vmp') : __('معطل', 'vmp'); ?>
                            </span>
                        </td>
                        <td>
                            <button class="button vmp-edit-plan" data-plan='<?php echo json_encode($plan); ?>'><?php _e('تعديل', 'vmp'); ?></button>
                            <button class="button vmp-delete-plan" data-id="<?php echo (int) $plan->id; ?>" data-nonce="<?php echo wp_create_nonce('vmp_admin_nonce'); ?>"><?php _e('حذف', 'vmp'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ════════════════════════════════════════════════ -->
    <!-- ✅ طلبات تغيير الخطة المعلقة -->
    <!-- ════════════════════════════════════════════════ -->
    <div class="vmp-admin-card" style="margin-top: 30px; background: #fff; border-radius: 8px; padding: 0 20px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div class="vmp-admin-card-header" style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding: 16px 0;">
            <h2 style="margin:0; font-size: 18px; font-weight: 600;">⏳ <?php _e('طلبات تغيير الخطة المعلقة', 'vmp'); ?></h2>
            <span class="vmp-admin-badge" id="vmp-pending-count" style="background: #6366f1; color: #fff; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600;">0</span>
        </div>
        <div class="vmp-admin-card-body" style="padding: 16px 0 0;">
            <div id="vmp-pending-requests">
                <p style="text-align:center; padding: 20px; color: #94a3b8;">
                    <?php _e('جاري تحميل الطلبات...', 'vmp'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════ -->
    <!-- مودال الإضافة / التعديل -->
    <!-- ════════════════════════════════════════════════ -->
    <div class="vmp-modal-overlay" id="vmp-plan-modal" style="display:none;">
        <div class="vmp-modal">
            <div class="vmp-modal-header">
                <h2 id="vmp-modal-title"><?php _e('إضافة خطة جديدة', 'vmp'); ?></h2>
                <button class="vmp-modal-close">&times;</button>
            </div>
            <div class="vmp-modal-body">
                <form id="vmp-plan-form">
                    <input type="hidden" name="plan_id" id="vmp_plan_id" value="0">
                    <?php wp_nonce_field('vmp_admin_nonce', 'nonce'); ?>

                    <!-- ── اسم الخطة ── -->
                    <div class="vmp-field-group">
                        <label><?php _e('اسم الخطة', 'vmp'); ?> <span class="required">*</span></label>
                        <input type="text" name="name" id="vmp_plan_name" class="vmp-field" required>
                    </div>

                    <!-- ── الوصف ── -->
                    <div class="vmp-field-group">
                        <label><?php _e('وصف الخطة', 'vmp'); ?></label>
                        <textarea name="description" id="vmp_plan_description" rows="2" class="vmp-field"></textarea>
                    </div>

                    <!-- ── السعر ودورة الدفع ── -->
                    <div class="vmp-row">
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('السعر', 'vmp'); ?> <span class="required">*</span></label>
                                <input type="number" step="0.01" name="price" id="vmp_plan_price" class="vmp-field" required>
                            </div>
                        </div>
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('دورة الدفع', 'vmp'); ?> <span class="required">*</span></label>
                                <select name="billing_period" id="vmp_plan_billing_period" class="vmp-field">
                                    <option value="month"><?php _e('شهري', 'vmp'); ?></option>
                                    <option value="year"><?php _e('سنوي', 'vmp'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('فترة الفوترة', 'vmp'); ?></label>
                                <input type="number" name="billing_interval" id="vmp_plan_billing_interval" class="vmp-field" value="1" min="1">
                                <span class="vmp-hint"><?php _e('عدد الأشهر/السنوات لكل دورة (افتراضي: 1)', 'vmp'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- ── العمولة والحد الأقصى ── -->
                    <div class="vmp-row">
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('نسبة العمولة (%)', 'vmp'); ?> <span class="required">*</span></label>
                                <input type="number" step="0.1" name="commission_rate" id="vmp_plan_commission_rate" class="vmp-field" value="10" required>
                                <span class="vmp-hint"><?php _e('النسبة التي يقتطعها الموقع.', 'vmp'); ?></span>
                            </div>
                        </div>
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('الحد الأقصى للمنتجات', 'vmp'); ?></label>
                                <input type="number" name="max_products" id="vmp_plan_max_products" class="vmp-field" value="0">
                                <span class="vmp-hint"><?php _e('0 = غير محدود', 'vmp'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- ── الحالة ── -->
                    <div class="vmp-field-group">
                        <label><?php _e('الحالة', 'vmp'); ?> <span class="required">*</span></label>
                        <select name="is_active" id="vmp_plan_is_active" class="vmp-field">
                            <option value="1"><?php _e('مفعل', 'vmp'); ?></option>
                            <option value="0"><?php _e('معطل', 'vmp'); ?></option>
                        </select>
                    </div>

                    <!-- ── المميزات (Toggle Buttons) ── -->
                    <div class="vmp-features-section">
                        <label><?php _e('المميزات', 'vmp'); ?></label>
                        <div class="vmp-features-grid">
                            <?php
                            $feature_list = [
                                'whatsapp_button'   => ['icon' => '💬', 'label' => __('طلب عبر واتساب', 'vmp')],
                                'store_address'     => ['icon' => '📍', 'label' => __('عنوان مع خريطة', 'vmp')],
                                'social_links'      => ['icon' => '🔗', 'label' => __('روابط التواصل', 'vmp')],
                                'product_video'     => ['icon' => '🎬', 'label' => __('فيديو تعريفي', 'vmp')],
                                'ai_product_generator' => ['icon' => '🤖', 'label' => __('إنشاء منتج بالذكاء الاصطناعي', 'vmp')],
                                'unlimited_products'=> ['icon' => '♾️', 'label' => __('منتجات غير محدودة', 'vmp')],
                                'custom_domain'     => ['icon' => '🌐', 'label' => __('نطاق مخصص', 'vmp')],
                                'advanced_analytics'=> ['icon' => '📊', 'label' => __('تحليلات متقدمة', 'vmp')],
                                'coupons'           => ['icon' => '🏷️', 'label' => __('كوبونات خصم', 'vmp')],
                                'trusted_badge'     => ['icon' => '⭐', 'label' => __('شارة موثوق', 'vmp')],
                                'priority_support'  => ['icon' => '🛟', 'label' => __('دعم أولوية', 'vmp')],
                            ];
                            foreach ($feature_list as $key => $feature) :
                            ?>
                                <label class="vmp-feature-toggle" data-feature="<?php echo esc_attr($key); ?>">
                                    <input type="checkbox" name="features[<?php echo esc_attr($key); ?>]" value="1" class="vmp-feature-input">
                                    <span class="vmp-toggle-slider"></span>
                                    <span class="vmp-feature-label">
                                        <span class="vmp-feature-icon"><?php echo esc_html($feature['icon']); ?></span>
                                        <?php echo esc_html($feature['label']); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="vmp-hint"><?php _e('اختر الميزات التي ستكون متاحة في هذه الخطة.', 'vmp'); ?></p>
                    </div>

                    <!-- ── أزرار الإجراء ── -->
                    <div class="vmp-actions">
                        <button type="button" class="vmp-btn vmp-btn-secondary vmp-modal-cancel"><?php _e('إلغاء', 'vmp'); ?></button>
                        <button type="submit" class="vmp-btn vmp-btn-primary" id="vmp-save-plan-btn">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('حفظ الخطة', 'vmp'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* ── الحاوية العامة ── */
.vmp-admin-wrap { max-width: 1100px; }

/* ── الإشعارات الإدارية ── */
.vmp-admin-notices .notice { border-left: 4px solid #6366f1; }
.vmp-admin-notices .notice-info { border-left-color: #3b82f6; }
.vmp-admin-notices .notice-success { border-left-color: #10b981; }
.vmp-admin-notices .notice-warning { border-left-color: #f59e0b; }
.vmp-admin-notices .notice-error { border-left-color: #ef4444; }

/* ── حالة الخطط ── */
.vmp-badge-status { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
.vmp-status-approved { background: #dcfce7; color: #166534; }
.vmp-status-rejected { background: #fee2e2; color: #991b1b; }
.vmp-status-pending { background: #fef3c7; color: #92400e; }

/* ── نموذج البطاقة الإدارية ── */
.vmp-admin-card { margin-top: 30px; background: #fff; border-radius: 8px; padding: 0 20px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.vmp-admin-card-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding: 16px 0; }
.vmp-admin-card-header h2 { margin: 0; font-size: 18px; font-weight: 600; }
.vmp-admin-badge { background: #6366f1; color: #fff; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }

/* ── مودال الإضافة/التعديل ── */
.vmp-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center; }
.vmp-modal { background: #fff; width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); animation: vmpModalIn 0.2s ease; }
@keyframes vmpModalIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
.vmp-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; }
.vmp-modal-header h2 { margin: 0; font-size: 18px; font-weight: 600; }
.vmp-modal-close { background: none; border: none; font-size: 24px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 0 4px; }
.vmp-modal-close:hover { color: #ef4444; }
.vmp-modal-body { padding: 24px; }

/* ── حقول النموذج ── */
.vmp-field-group { margin-bottom: 16px; }
.vmp-field-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #334155; }
.vmp-field-group label .required { color: #ef4444; margin-left: 4px; }
.vmp-field { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border-color 0.15s, box-shadow 0.15s; }
.vmp-field:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.vmp-hint { display: block; margin-top: 4px; font-size: 12px; color: #94a3b8; }

/* ── تخطيط الصفوف والأعمدة ── */
.vmp-row { display: flex; gap: 16px; margin-bottom: 0; }
.vmp-col { flex: 1; }
@media (max-width: 768px) { .vmp-row { flex-direction: column; } .vmp-col { width: 100%; } }

/* ── قسم المميزات ── */
.vmp-features-section { margin-top: 24px; padding-top: 24px; border-top: 1px solid #e2e8f0; }
.vmp-features-section > label { display: block; margin-bottom: 12px; font-weight: 500; color: #334155; }
.vmp-features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.vmp-feature-toggle { position: relative; display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.15s; }
.vmp-feature-toggle:hover { background: #f1f5f9; border-color: #cbd5e1; }
.vmp-feature-toggle:has(input:checked) { background: #eef2ff; border-color: #6366f1; }
.vmp-feature-input { position: absolute; opacity: 0; pointer-events: none; }
.vmp-toggle-slider { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 4px; flex-shrink: 0; transition: all 0.15s; position: relative; }
.vmp-feature-input:checked + .vmp-toggle-slider { background: #6366f1; border-color: #6366f1; }
.vmp-feature-input:checked + .vmp-toggle-slider::after { content: ""; position: absolute; left: 6px; top: 2px; width: 5px; height: 10px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg); }
.vmp-feature-label { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; }
.vmp-feature-icon { font-size: 14px; }

/* ── أزرار ── */
.vmp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 18px; border-radius: 6px; font-size: 14px; font-weight: 500; border: none; cursor: pointer; transition: background 0.15s, transform 0.05s; }
.vmp-btn:active { transform: scale(0.98); }
.vmp-btn-primary { background: #6366f1; color: #fff; }
.vmp-btn-primary:hover { background: #4f46e5; }
.vmp-btn-secondary { background: #f1f5f9; color: #475569; }
.vmp-btn-secondary:hover { background: #e2e8f0; }
.vmp-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.vmp-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; }

/* ── جدول الطلبات المعلقة ── */
#vmp-pending-requests table { width: 100%; }
#vmp-pending-requests th, #vmp-pending-requests td { padding: 10px 12px; }
#vmp-pending-requests th { background: #f8fafc; font-weight: 600; color: #334155; border-bottom: 1px solid #e2e8f0; }
#vmp-pending-requests td { border-bottom: 1px solid #f1f5f9; }
#vmp-pending-requests button { margin-right: 4px; }
</style>

<!-- expose admin nonce to JS -->
<script>var vmp_admin = { nonce: '<?php echo wp_create_nonce('vmp_admin_nonce'); ?>' };</script>

<!-- JavaScript -->
<script>
jQuery(document).ready(function($) {
    'use strict';

    // ── فتح المودال للإضافة (جديد) ──
    $(document).on('click', '.vmp-open-modal', function(e) {
        e.preventDefault();
        // إعادة تعيين النموذج
        $('#vmp-plan-form')[0].reset();
        $('#vmp_plan_id').val('0');
        $('#vmp_plan_max_products').val('0');
        $('#vmp_plan_commission_rate').val('10');
        $('#vmp_plan_is_active').val('1');
        $('#vmp_plan_billing_interval').val('1');
        $('.vmp-feature-input').prop('checked', false);

        $('#vmp-modal-title').text('<?php _e('إضافة خطة جديدة', 'vmp'); ?>');
        $('#vmp-plan-modal').show();
    });

    // ── إغلاق المودال ──
    $(document).on('click', '.vmp-modal-close, .vmp-modal-cancel', function(e) {
        e.preventDefault();
        $('#vmp-plan-modal').hide();
    });

    // إغلاق المودال عند الضغط خارجه
    $(document).on('click', '#vmp-plan-modal', function(e) {
        if (e.target === this) {
            $('#vmp-plan-modal').hide();
        }
    });

    // ── حفظ الخطة (إضافة/تعديل) ──
    $(document).on('submit', '#vmp-plan-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $('#vmp-save-plan-btn');
        var planId = $('#vmp_plan_id').val();
        var isEdit = planId && planId !== '0';

        $btn.prop('disabled', true).text('<?php _e('جاري الحفظ...', 'vmp'); ?>');

        // جمع البيانات
        var formData = {
            action: isEdit ? 'vmp_admin_update_plan' : 'vmp_admin_create_plan',
            nonce: vmp_admin.nonce,
            plan_id: planId,
            name: $('#vmp_plan_name').val(),
            description: $('#vmp_plan_description').val(),
            price: $('#vmp_plan_price').val(),
            billing_period: $('#vmp_plan_billing_period').val(),
            billing_interval: $('#vmp_plan_billing_interval').val(),
            commission_rate: $('#vmp_plan_commission_rate').val(),
            max_products: $('#vmp_plan_max_products').val(),
            is_active: $('#vmp_plan_is_active').val(),
        };

        // جمع المميزات
        var features = {};
        $('.vmp-feature-input:checked').each(function() {
            var key = $(this).attr('name').match(/features\[(.+?)\]/);
            if (key) {
                features[key[1]] = true;
            }
        });
        formData.features = features;

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                alert(response.data.message || (isEdit ? '<?php _e('تم تحديث الخطة بنجاح', 'vmp'); ?>' : '<?php _e('تم إنشاء الخطة بنجاح', 'vmp'); ?>'));
                $('#vmp-plan-modal').hide();
                location.reload();
            } else {
                alert(response.data.message || '<?php _e('حدث خطأ أثناء الحفظ', 'vmp'); ?>');
                $btn.prop('disabled', false).text('<?php _e('حفظ الخطة', 'vmp'); ?>');
            }
        }).fail(function(xhr) {
            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : '<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>';
            alert(msg);
            $btn.prop('disabled', false).text('<?php _e('حفظ الخطة', 'vmp'); ?>');
        });
    });

    // ── حذف الخطة ──
    $(document).on('click', '.vmp-delete-plan', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var planId = $btn.data('id');
        var nonce = $btn.data('nonce');

        if (!confirm('<?php _e('هل أنت متأكد من حذف هذه الخطة؟', 'vmp'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('جاري الحذف...', 'vmp'); ?>');

        $.post(ajaxurl, {
            action: 'vmp_admin_delete_plan',
            nonce: nonce,
            plan_id: planId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('تم الحذف بنجاح', 'vmp'); ?>');
                location.reload();
            } else {
                alert(response.data.message || '<?php _e('حدث خطأ أثناء الحذف', 'vmp'); ?>');
                $btn.prop('disabled', false).text('<?php _e('حذف', 'vmp'); ?>');
            }
        }).fail(function(xhr) {
            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : '<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>';
            alert(msg);
            $btn.prop('disabled', false).text('<?php _e('حذف', 'vmp'); ?>');
        });
    });

    // ── فتح المودال للتعديل ──
    $(document).on('click', '.vmp-edit-plan', function(e) {
        e.preventDefault();
        var plan = $(this).data('plan');
        if (!plan) return;

        $('#vmp_plan_id').val(plan.id);
        $('#vmp_plan_name').val(plan.name);
        $('#vmp_plan_description').val(plan.description || '');
        $('#vmp_plan_price').val(plan.price);
        $('#vmp_plan_billing_period').val(plan.billing_period);
        $('#vmp_plan_billing_interval').val(plan.billing_interval);
        $('#vmp_plan_commission_rate').val(plan.commission_rate);
        $('#vmp_plan_max_products').val(plan.max_products);
        $('#vmp_plan_is_active').val(plan.is_active);

        // تعبئة الـ Toggles من الميزات
        var features = plan.features ? JSON.parse(plan.features) : {};
        $('.vmp-feature-input').prop('checked', false);
        $.each(features, function(key, value) {
            if (value === true || value === 1) {
                $('input[name="features[' + key + ']"]').prop('checked', true);
            }
        });

        $('#vmp-modal-title').text('<?php _e('تعديل الخطة', 'vmp'); ?>');
        $('#vmp-plan-modal').show();
    });

    // ════════════════════════════════════════════════
    // ✅ طلبات تغيير الخطة المعلقة
    // ════════════════════════════════════════════════
    function loadPendingRequests() {
        $.post(ajaxurl, {
            action: 'vmp_get_pending_plan_changes',
            nonce: vmp_admin.nonce
        }, function(response) {
            if (response.success && response.data.requests) {
                var requests = response.data.requests;
                var html = '';

                if (requests.length === 0) {
                    html = '<p style="text-align:center; padding: 20px; color: #94a3b8;">' +
                           '<?php _e('لا توجد طلبات معلقة.', 'vmp'); ?>' +
                           '</p>';
                } else {
                    html = '<table class="wp-list-table widefat fixed striped">' +
                           '<thead><tr>' +
                           '<th><?php _e('البائع', 'vmp'); ?></th>' +
                           '<th><?php _e('الخطة المطلوبة', 'vmp'); ?></th>' +
                           '<th><?php _e('السعر', 'vmp'); ?></th>' +
                           '<th><?php _e('التاريخ', 'vmp'); ?></th>' +
                           '<th><?php _e('إجراءات', 'vmp'); ?></th>' +
                           '</tr></thead><tbody>';

                    $.each(requests, function(i, req) {
                        var date = new Date(req.created_at);
                        var formattedDate = date.toLocaleDateString('ar-SA');

                        html += '<tr>' +
                                '<td><strong>' + req.store_name + '</strong></td>' +
                                '<td>' + req.plan_name + '</td>' +
                                '<td>' + req.plan_price + '</td>' +
                                '<td>' + formattedDate + '</td>' +
                                '<td>' +
                                '<button class="button button-primary vmp-approve-change" data-id="' + req.id + '">' +
                                '<?php _e('موافقة', 'vmp'); ?>' +
                                '</button> ' +
                                '<button class="button vmp-reject-change" data-id="' + req.id + '">' +
                                '<?php _e('رفض', 'vmp'); ?>' +
                                '</button>' +
                                '</td>' +
                                '</tr>';
                    });

                    html += '</tbody></table>';
                }

                $('#vmp-pending-requests').html(html);
                $('#vmp-pending-count').text(requests.length);
            } else {
                var msg = (response.data && response.data.message) ? response.data.message : '<?php _e('حدث خطأ في تحميل الطلبات.', 'vmp'); ?>';
                $('#vmp-pending-requests').html('<p style="text-align:center; padding: 20px; color: #94a3b8;">' + msg + '</p>');
            }
        }).fail(function(xhr) {
            var body = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : xhr.responseText || '<?php _e('خطأ في الاتصال.', 'vmp'); ?>';
            $('#vmp-pending-requests').html('<p style="text-align:center; padding: 20px; color: #94a3b8;">' + body + '</p>');
        });
    }

    // ── تحميل الطلبات عند فتح الصفحة ──
    loadPendingRequests();

    // ── تحديث الطلبات كل 30 ثانية ──
    setInterval(loadPendingRequests, 30000);

    // ── الموافقة على طلب تغيير الخطة ──
    $(document).on('click', '.vmp-approve-change', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var requestId =
        $btn.data('id');

        if (!confirm('<?php _e('هل أنت متأكد من الموافقة على هذا الطلب؟', 'vmp'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('جاري...', 'vmp'); ?>');

        $.post(ajaxurl, {
            action: 'vmp_admin_approve_plan_change',
            nonce: vmp_admin.nonce,
            request_id: requestId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('تمت الموافقة بنجاح', 'vmp'); ?>');
                loadPendingRequests();
            } else {
                alert(response.data.message || '<?php _e('حدث خطأ', 'vmp'); ?>');
                $btn.prop('disabled', false).text('<?php _e('موافقة', 'vmp'); ?>');
            }
        }).fail(function(xhr) {
            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : '<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>';
            alert(msg);
            $btn.prop('disabled', false).text('<?php _e('موافقة', 'vmp'); ?>');
        });
    });

    // ── رفض طلب تغيير الخطة ──
    $(document).on('click', '.vmp-reject-change', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var requestId = $btn.data('id');
        var reason = prompt('<?php _e('أدخل سبب الرفض (اختياري):', 'vmp'); ?>');

        if (!confirm('<?php _e('هل أنت متأكد من رفض هذا الطلب؟', 'vmp'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('جاري...', 'vmp'); ?>');

        $.post(ajaxurl, {
            action: 'vmp_admin_reject_plan_change',
            nonce: vmp_admin.nonce,
            request_id: requestId,
            reason: reason || ''
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('تم الرفض', 'vmp'); ?>');
                loadPendingRequests();
            } else {
                alert(response.data.message || '<?php _e('حدث خطأ', 'vmp'); ?>');
                $btn.prop('disabled', false).text('<?php _e('رفض', 'vmp'); ?>');
            }
        }).fail(function(xhr) {
            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : '<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>';
            alert(msg);
            $btn.prop('disabled', false).text('<?php _e('رفض', 'vmp'); ?>');
        });
    });
});
</script>
