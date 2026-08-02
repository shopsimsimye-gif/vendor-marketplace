<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من وجود البائع ──
$user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
if (!$user_id) {
    $login_message = function_exists('__') ? __('يرجى تسجيل الدخول أولاً.', 'vmp') : 'يرجى تسجيل الدخول أولاً.';
    echo '<div class="vmp-notice vmp-notice-error">' . esc_html($login_message) . '</div>';
    return;
}

// ── استخدام الحاوية للحصول على المستودعات (Dependency Injection) ──
// ✅ استخدام الواجهات (Contracts) بدلاً من الـ Concrete classes
//    للاستفادة من الـ Cached decorators المسجلة في الحاوية
$container = \VMP\Core\Container::getInstance();
$vendor_repo   = $container->make(\VMP\Contracts\VendorRepositoryInterface::class);
$order_repo    = $container->make(\VMP\Contracts\OrderRepositoryInterface::class);
$product_repo  = $container->make(\VMP\Contracts\ProductRepositoryInterface::class);
$sub_repo      = $container->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
$plan_repo     = $container->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);

// ── جلب بيانات البائع ──
$vendor = $vendor_repo->findByUserId($user_id);
if (!$vendor) {
    $vendor_missing_message = function_exists('__') ? __('البائع غير موجود.', 'vmp') : 'البائع غير موجود.';
    echo '<div class="vmp-notice vmp-notice-error">' . esc_html($vendor_missing_message) . '</div>';
    return;
}

$vendor_id = (int) $vendor->id;

// ── الإحصائيات (مع التخزين المؤقت) ──
$cache_key = 'vmp_dashboard_stats_' . $vendor_id;
$stats = function_exists('get_transient') ? get_transient($cache_key) : false;

if (false === $stats) {
    $total_products = $product_repo->countByVendor($vendor_id);
    $total_orders   = $order_repo->countByVendor($vendor_id, 'completed');
    $total_earnings = $order_repo->getTotalEarningsByVendor($vendor_id);
    $latest_orders  = $order_repo->getByVendor($vendor_id, [
        'limit'  => 5,
        'offset' => 0,
        'status' => 'all',
    ]);
    $active_sub = $sub_repo->findActiveByVendor($vendor_id);
    $plan = $active_sub ? $plan_repo->find((int) $active_sub->plan_id) : null;

    $stats = [
        'total_products' => $total_products,
        'total_orders'   => $total_orders,
        'total_earnings' => $total_earnings,
        'latest_orders'  => $latest_orders,
        'active_sub'     => $active_sub,
        'plan'           => $plan,
    ];
    if (function_exists('set_transient')) {
        set_transient($cache_key, $stats, 300);
    }
} else {
    $total_products = $stats['total_products'];
    $total_orders   = $stats['total_orders'];
    $total_earnings = $stats['total_earnings'];
    $latest_orders  = $stats['latest_orders'];
    $active_sub     = $stats['active_sub'];
    $plan           = $stats['plan'];
}

// ── مسار ملف التنقل (آمن) ──
$nav_file = VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php';
?>

<div class="vmp-wrap">
    <!-- شريط التنقل -->
    <?php if (file_exists($nav_file)) : ?>
        <?php include $nav_file; ?>
    <?php else : ?>
        <div class="vmp-notice vmp-notice-warning">
            <?php _e('ملف التنقل غير موجود.', 'vmp'); ?>
        </div>
    <?php endif; ?>

    <!-- رأس الصفحة -->
    <div class="vmp-header-bar">
        <h1><?php printf(__('أهلاً بك، %s!', 'vmp'), esc_html($vendor->store_name)); ?></h1>
        <p><?php _e('هنا نظرة عامة على أداء متجرك وإحصائيات مبيعاتك.', 'vmp'); ?></p>
        
        <?php if ($vendor->status === 'pending') : ?>
            <div class="vmp-badge" style="background:#f59e0b; border-color:#f59e0b; color:#fff;">
                <span class="dashicons dashicons-clock"></span> <?php _e('حسابك قيد المراجعة', 'vmp'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- بطاقات الإحصائيات -->
    <div class="vmp-stats-grid">
        <div class="vmp-stat-card">
            <div class="vmp-stat-icon" style="color:var(--vmp-primary); background:var(--vmp-primary-light);">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="vmp-stat-value"><?php echo function_exists('wc_price') ? wc_price($vendor->balance ?? 0) : esc_html($vendor->balance ?? 0); ?></div>
            <div class="vmp-stat-label"><?php _e('الرصيد المتاح للسحب', 'vmp'); ?></div>
        </div>
        <div class="vmp-stat-card">
            <div class="vmp-stat-icon" style="color:var(--vmp-success); background:rgba(16,185,129,0.1);">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="vmp-stat-value"><?php echo function_exists('wc_price') ? wc_price($total_earnings ?? 0) : esc_html($total_earnings ?? 0); ?></div>
            <div class="vmp-stat-label"><?php _e('إجمالي الأرباح', 'vmp'); ?></div>
        </div>
        <div class="vmp-stat-card">
            <div class="vmp-stat-icon" style="color:var(--vmp-warning); background:rgba(245,158,11,0.1);">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="vmp-stat-value"><?php echo function_exists('number_format_i18n') ? number_format_i18n($total_orders ?? 0) : number_format((int) ($total_orders ?? 0)); ?></div>
            <div class="vmp-stat-label"><?php _e('الطلبات المكتملة', 'vmp'); ?></div>
        </div>
        <div class="vmp-stat-card">
            <div class="vmp-stat-icon" style="color:var(--vmp-info); background:rgba(59,130,246,0.1);">
                <span class="dashicons dashicons-products"></span>
            </div>
            <div class="vmp-stat-value"><?php echo function_exists('number_format_i18n') ? number_format_i18n($total_products ?? 0) : number_format((int) ($total_products ?? 0)); ?></div>
            <div class="vmp-stat-label"><?php _e('المنتجات', 'vmp'); ?></div>
        </div>
    </div>

    <!-- الشبكة الرئيسية (رسم بياني + معلومات الخطة والطلبات) -->
    <div class="vmp-dashboard-grid">
        <!-- الرسم البياني -->
        <div class="vmp-card">
            <div class="vmp-card-header">
                <h2 class="vmp-card-title"><?php _e('أداء المبيعات', 'vmp'); ?></h2>
            </div>
            <div class="vmp-chart-container">
                <?php if (class_exists('VMP\Modules\Report')) : ?>
                    <canvas id="vmp-vendor-chart"></canvas>
                <?php else : ?>
                    <div class="vmp-chart-placeholder">
                        <p><?php _e('الرسم البياني قريباً', 'vmp'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- العمود الأيمن: الخطة + الطلبات الأخيرة + إشعارات -->
        <div>
            <!-- بطاقة الخطة -->
            <div class="vmp-card vmp-plan-card-highlight">
                <div class="vmp-card-header" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                    <h2 class="vmp-card-title"><?php _e('خطة الاشتراك الحالية', 'vmp'); ?></h2>
                </div>
                <div class="vmp-plan-content">
                    <?php if ($active_sub && $plan) : ?>
                        <div class="vmp-plan-name-large"><?php echo esc_html($plan->name); ?></div>
                        <ul class="vmp-plan-details">
                            <li>
                                <strong><?php _e('تاريخ التجديد:', 'vmp'); ?></strong>
                                <?php 
                                $end_date = !empty($active_sub->end_date) 
                                    ? date_i18n('Y-m-d', strtotime($active_sub->end_date)) 
                                    : '-';
                                echo esc_html($end_date);
                                ?>
                            </li>
                            <li>
                                <strong><?php _e('نسبة العمولة:', 'vmp'); ?></strong>
                                <?php echo esc_html($plan->commission_rate) . '%'; ?>
                            </li>
                        </ul>
                        <a href="?vmp_page=subscriptions" class="vmp-btn vmp-btn-primary vmp-btn-block vmp-btn-sm">
                            <?php _e('ترقية الخطة', 'vmp'); ?>
                        </a>
                    <?php else : ?>
                        <div class="vmp-notice vmp-notice-warning" style="margin: 0 0 16px;">
                            <?php _e('ليس لديك خطة اشتراك نشطة.', 'vmp'); ?>
                        </div>
                        <a href="?vmp_page=subscriptions" class="vmp-btn vmp-btn-primary vmp-btn-block vmp-btn-sm">
                            <?php _e('اشترك الآن', 'vmp'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- أحدث الطلبات -->
            <div class="vmp-card">
                <div class="vmp-card-header">
                    <h2 class="vmp-card-title"><?php _e('أحدث الطلبات', 'vmp'); ?></h2>
                    <a href="?vmp_page=orders" class="vmp-link-small"><?php _e('عرض الكل', 'vmp'); ?></a>
                </div>
                <?php if (empty($latest_orders)) : ?>
                    <p class="vmp-empty-orders"><?php _e('لا توجد طلبات بعد.', 'vmp'); ?></p>
                <?php else : ?>
                    <ul class="vmp-order-list">
                        <?php foreach ($latest_orders as $order) : 
                            $order_date = !empty($order->created_at) 
                                ? date_i18n('M j', strtotime($order->created_at)) 
                                : '-';
                        ?>
                            <li class="vmp-order-item">
                                <div>
                                    <strong class="vmp-order-id">#<?php echo esc_html($order->order_id); ?></strong>
                                    <span class="vmp-order-date"><?php echo esc_html($order_date); ?></span>
                                </div>
                                <div class="vmp-order-earnings">
                                    <strong><?php echo function_exists('wc_price') ? wc_price($order->vendor_earnings ?? 0) : esc_html($order->vendor_earnings ?? 0); ?></strong>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- ✅ إشعارات البائع -->
            <div class="vmp-card">
                <div class="vmp-card-header">
                    <h2 class="vmp-card-title">🔔 <?php _e('إشعاراتي', 'vmp'); ?></h2>
                    <button id="vmp-mark-all-read" class="vmp-btn vmp-btn-outline vmp-btn-sm">
                        <?php _e('تحديد الكل كمقروء', 'vmp'); ?>
                    </button>
                </div>
                <div id="vmp-vendor-notices">
                    <?php
                    $notices = get_user_meta($vendor_id, 'vmp_dashboard_notices', true);
                    if (empty($notices)) : ?>
                        <p style="text-align:center; color:var(--vmp-text-muted); padding: 20px;">
                            <?php _e('لا توجد إشعارات.', 'vmp'); ?>
                        </p>
                    <?php else : 
                        // عرض أحدث الإشعارات أولاً
                        usort($notices, function($a, $b) {
                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                        });
                        $notices = array_slice($notices, 0, 10);
                    ?>
                        <ul class="vmp-notices-list">
                            <?php foreach ($notices as $notice) : ?>
                                <li class="vmp-notice-item <?php echo $notice['read'] ? 'vmp-notice-read' : 'vmp-notice-unread'; ?>">
                                    <div class="vmp-notice-icon">
                                        <?php echo $notice['type'] === 'success' ? '✅' : '❌'; ?>
                                    </div>
                                    <div class="vmp-notice-content">
                                        <strong><?php echo esc_html($notice['title']); ?></strong>
                                        <p><?php echo nl2br(esc_html($notice['message'])); ?></p>
                                        <span class="vmp-notice-date"><?php echo date_i18n('Y-m-d H:i', strtotime($notice['created_at'])); ?></span>
                                    </div>
                                    <?php if (!$notice['read']) : ?>
                                        <button class="vmp-btn vmp-btn-sm vmp-notice-mark-read" data-notice-id="<?php echo esc_attr($notice['id']); ?>">
                                            <?php _e('تحديد كمقروء', 'vmp'); ?>
                                        </button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ JavaScript للإشعارات -->
<script>
jQuery(document).ready(function($) {
    // ── تحديد إشعار كمقروء ──
    $(document).on('click', '.vmp-notice-mark-read', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var noticeId = $btn.data('notice-id');

        $.post(vmp_public.ajax_url, {
            action: 'vmp_mark_notice_read',
            nonce: vmp_public.nonce,
            notice_id: noticeId
        }, function(response) {
            if (response.success) {
                $btn.closest('.vmp-notice-item').addClass('vmp-notice-read');
                $btn.remove();
            }
        });
    });

    // ── تحديد الكل كمقروء ──
    $('#vmp-mark-all-read').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);

        $.post(vmp_public.ajax_url, {
            action: 'vmp_mark_all_notices_read',
            nonce: vmp_public.nonce
        }, function(response) {
            if (response.success) {
                $('.vmp-notice-item').addClass('vmp-notice-read');
                $('.vmp-notice-mark-read').remove();
            }
        });
    });
});
</script>

<style>
.vmp-notices-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.vmp-notice-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--vmp-border);
    transition: background 0.2s;
}
.vmp-notice-item:hover {
    background: var(--vmp-bg);
}
.vmp-notice-item.vmp-notice-unread {
    border-right: 3px solid var(--vmp-primary);
    background: var(--vmp-primary-light);
}
.vmp-notice-icon {
    font-size: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}
.vmp-notice-content {
    flex: 1;
}
.vmp-notice-content strong {
    display: block;
    font-size: 14px;
}
.vmp-notice-content p {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--vmp-text-muted);
}
.vmp-notice-date {
    font-size: 11px;
    color: var(--vmp-text-light);
    margin-top: 4px;
    display: block;
}
</style>