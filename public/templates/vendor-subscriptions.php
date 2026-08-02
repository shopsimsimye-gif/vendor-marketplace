<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── جلب بيانات البائع ──
$user_id = get_current_user_id();
$vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
$vendor = $vendor_repo->findByUserId($user_id);

if (!$vendor) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('البائع غير موجود.', 'vmp') . '</div>';
    return;
}

// ── المستودعات ──
$sub_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
$plan_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);

$vendor_id = (int) $vendor->id;
$active_sub = $sub_repo->findActiveByVendor($vendor_id);
$all_plans = $plan_repo->getAll(true);

// ── طلب تغيير خطة معلق ──
$pending_change = $sub_repo->getPendingPlanChangeByVendor($vendor_id);

$nav_file = VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php';
?>

<div class="vmp-wrap">
    <?php if (file_exists($nav_file)) include $nav_file; ?>

    <div class="vmp-card">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php _e('خطط الاشتراك', 'vmp'); ?></h2>
        </div>

        <!-- ✅ عرض حالة طلب التغيير المعلق -->
        <?php if ($pending_change) : ?>
            <div class="vmp-notice vmp-notice-warning" style="margin-bottom: 24px;">
                <strong>⏳ <?php _e('طلب تغيير خطة قيد المراجعة', 'vmp'); ?></strong>
                <p style="margin: 8px 0 0;">
                    <?php _e('تم إرسال طلب تغيير خطتك وهو قيد المراجعة من قبل المشرف.', 'vmp'); ?>
                    <?php _e('لن تتمكن من استخدام ميزات الخطة الجديدة حتى تتم الموافقة.', 'vmp'); ?>
                </p>
                <button class="vmp-btn vmp-btn-danger vmp-btn-sm vmp-cancel-plan-change" style="margin-top: 12px;">
                    <?php _e('إلغاء الطلب', 'vmp'); ?>
                </button>
            </div>
        <?php endif; ?>

        <!-- الاشتراك الحالي -->
        <?php if ($active_sub) :
            $current_plan = $plan_repo->find((int) $active_sub->plan_id);
            $end_date = !empty($active_sub->end_date) ? date_i18n('Y-m-d', strtotime($active_sub->end_date)) : '-';
        ?>
            <div class="vmp-notice vmp-notice-success">
                <?php printf(
                    __('اشتراكك الحالي: <strong>%s</strong> — ينتهي في %s', 'vmp'),
                    esc_html($current_plan ? $current_plan->name : __('غير معروف', 'vmp')),
                    esc_html($end_date)
                ); ?>
            </div>
        <?php else : ?>
            <div class="vmp-notice vmp-notice-info">
                <?php _e('ليس لديك خطة اشتراك نشطة. اختر خطة من القائمة أدناه.', 'vmp'); ?>
            </div>
        <?php endif; ?>

        <!-- قائمة الخطط -->
        <div class="vmp-plans-grid">
            <?php if (empty($all_plans)) : ?>
                <div class="vmp-empty">
                    <p><?php _e('لا توجد خطط اشتراك متاحة حالياً.', 'vmp'); ?></p>
                </div>
            <?php else : ?>
                <?php foreach ($all_plans as $plan) :
                    $is_current = ($active_sub && (int) $active_sub->plan_id === (int) $plan->id);
                    $is_pending = ($pending_change && (int) $pending_change->plan_id === (int) $plan->id);
                    $features = is_string($plan->features) ? json_decode($plan->features, true) : [];
                    $is_free = ((float) $plan->price == 0);
                ?>
                    <div class="vmp-plan-card <?php echo $is_current ? 'vmp-plan-current' : ''; ?> <?php echo $is_free ? 'vmp-plan-free' : ''; ?>">
                        <?php if ($is_current) : ?>
                            <div class="vmp-plan-badge"><?php _e('حالياً', 'vmp'); ?></div>
                        <?php endif; ?>
                        <?php if ($is_pending) : ?>
                            <div class="vmp-plan-badge" style="background:#f59e0b;"><?php _e('قيد المراجعة', 'vmp'); ?></div>
                        <?php endif; ?>
                        <?php if ($is_free) : ?>
                            <div class="vmp-plan-badge vmp-plan-badge-free"><?php _e('مجاني', 'vmp'); ?></div>
                        <?php endif; ?>

                        <div class="vmp-plan-header">
                            <div class="vmp-plan-name"><?php echo esc_html($plan->name); ?></div>
                            <div class="vmp-plan-price">
                                <?php if ($is_free) : ?>
                                    <span class="vmp-plan-price-free"><?php _e('مجاني', 'vmp'); ?></span>
                                <?php else : ?>
                                    <?php echo wc_price($plan->price); ?>
                                    <span class="vmp-plan-price-period">
                                        / <?php echo $plan->billing_period === 'month' ? __('شهر', 'vmp') : __('سنة', 'vmp'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="vmp-plan-description"><?php echo esc_html($plan->description ?? ''); ?></p>

                        <ul class="vmp-plan-features">
                            <li>
                                <span class="vmp-plan-feature-icon">📦</span>
                                <?php printf(
                                    __('%s منتج', 'vmp'),
                                    (int) $plan->max_products === -1 ? __('غير محدود', 'vmp') : (int) $plan->max_products
                                ); ?>
                            </li>
                            <li>
                                <span class="vmp-plan-feature-icon">📊</span>
                                <?php printf(__('عمولة %s%%', 'vmp'), (float) $plan->commission_rate); ?>
                            </li>
                            <?php if (!empty($features) && is_array($features)) : ?>
                                <?php foreach ($features as $key => $value) : ?>
                                    <?php if ($value === true || $value === 1 || $value === '1') : ?>
                                        <li>
                                            <span class="vmp-plan-feature-icon">⭐</span>
                                            <?php 
                                                $feature_labels = [
                                                    'whatsapp_button' => __('طلب عبر واتساب', 'vmp'),
                                                    'store_address'   => __('عنوان المتجر مع خريطة', 'vmp'),
                                                    'social_links'    => __('روابط التواصل الاجتماعي', 'vmp'),
                                                    'product_video'   => __('فيديو تعريفي', 'vmp'),
                                                    'unlimited_products' => __('منتجات غير محدودة', 'vmp'),
                                                    'custom_domain'   => __('نطاق مخصص', 'vmp'),
                                                    'advanced_analytics' => __('تحليلات متقدمة', 'vmp'),
                                                ];
                                                echo esc_html($feature_labels[$key] ?? $key);
                                            ?>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>

                        <div class="vmp-plan-action">
                            <?php if ($is_current) : ?>
                                <button class="vmp-btn vmp-btn-outline vmp-btn-block" disabled>
                                    <?php _e('خطتك الحالية', 'vmp'); ?>
                                </button>
                            <?php elseif ($is_pending) : ?>
                                <button class="vmp-btn vmp-btn-warning vmp-btn-block" disabled>
                                    ⏳ <?php _e('قيد المراجعة', 'vmp'); ?>
                                </button>
                            <?php else : ?>
                                <button class="vmp-btn vmp-btn-primary vmp-btn-block vmp-btn-request-plan-change" 
                                        data-plan-id="<?php echo (int) $plan->id; ?>"
                                        data-plan-name="<?php echo esc_attr($plan->name); ?>">
                                    <?php _e('طلب تغيير الخطة', 'vmp'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ✅ JavaScript لمعالجة طلب تغيير الخطة -->
<script>
jQuery(document).ready(function($) {
    'use strict';

    // ── طلب تغيير الخطة ──
    $(document).on('click', '.vmp-btn-request-plan-change', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var planId = $btn.data('plan-id');
        var planName = $btn.data('plan-name');

        if (!confirm('<?php _e('هل أنت متأكد من طلب تغيير خطتك إلى', 'vmp'); ?> "' + planName + '"? <?php _e('سيتم مراجعة الطلب من قبل المشرف.', 'vmp'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('جاري الإرسال...', 'vmp'); ?>');
        $('.vmp-loading').addClass('show');

        $.post(vmp_public.ajax_url, {
            action: 'vmp_request_plan_change',
            nonce: vmp_public.nonce,
            plan_id: planId
        }, function(response) {
            $('.vmp-loading').removeClass('show');
            $btn.prop('disabled', false).text('<?php _e('طلب تغيير الخطة', 'vmp'); ?>');

            if (response.success) {
                VMP.showNotice(response.data.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                VMP.showNotice(response.data.message, 'error');
            }
        }).fail(function() {
            $('.vmp-loading').removeClass('show');
            $btn.prop('disabled', false).text('<?php _e('طلب تغيير الخطة', 'vmp'); ?>');
            VMP.showNotice('<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>', 'error');
        });
    });

    // ── إلغاء طلب تغيير الخطة ──
    $(document).on('click', '.vmp-cancel-plan-change', function(e) {
        e.preventDefault();

        if (!confirm('<?php _e('هل أنت متأكد من إلغاء طلب تغيير الخطة؟', 'vmp'); ?>')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('جاري...', 'vmp'); ?>');
        $('.vmp-loading').addClass('show');

        $.post(vmp_public.ajax_url, {
            action: 'vmp_cancel_plan_change',
            nonce: vmp_public.nonce
        }, function(response) {
            $('.vmp-loading').removeClass('show');
            $btn.prop('disabled', false).text('<?php _e('إلغاء الطلب', 'vmp'); ?>');

            if (response.success) {
                VMP.showNotice(response.data.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                VMP.showNotice(response.data.message, 'error');
            }
        }).fail(function() {
            $('.vmp-loading').removeClass('show');
            $btn.prop('disabled', false).text('<?php _e('إلغاء الطلب', 'vmp'); ?>');
            VMP.showNotice('<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>', 'error');
        });
    });
});
</script>