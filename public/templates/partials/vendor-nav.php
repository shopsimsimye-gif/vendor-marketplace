<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Partial: vendor navigation
 *
 * التحسينات:
 * - استخدام permalink للوحة التحكم إن وُجد في الإعدادات (vmp_settings.display.dashboard_page)
 * - بناء روابط dashboard كاملة (absolute URLs) بدلاً من الاعتماد على "?vmp_page=..."
 * - تحسين أمان/تطهير البيانات وescaping للمخرجات
 */

/* ====== اكتشاف الصفحة الحالية ====== */
// نفضّل استخدام query_var أولاً لأننا نسجّله في rewrite/query handling
$current_page = get_query_var('vmp_page', '');
if (!$current_page) {
    // fallback إلى $_GET إن لم تتوفر query_var
    $current_page = isset($_GET['vmp_page']) ? sanitize_text_field(wp_unslash($_GET['vmp_page'])) : 'dashboard';
}
$current_page = $current_page ?: 'dashboard';

/* ====== تحضير dashboard_url آمن ====== */
// نحاول قراءة الإعداد إذا كان مهيأً ضمن خيارات الإضافة
$dashboard_url = home_url('/vendor-dashboard/'); // fallback
$vmp_settings = get_option('vmp_settings', []);
$page_id = !empty($vmp_settings['display']['dashboard_page']) ? (int) $vmp_settings['display']['dashboard_page'] : 0;
if ($page_id && get_post($page_id)) {
    $permalink = get_permalink($page_id);
    if ($permalink) {
        $dashboard_url = $permalink;
    }
}

// الآن نستخدم add_query_arg لبناء روابط داخل لوحة التحكم (منتجات، طلبات، الخ)
$dashboard_url = esc_url_raw($dashboard_url); // sanitized base

/* ====== الحصول على البائع الحالي (إن وُجد) ====== */
$user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
$vendor = null;
if ($user_id) {
    try {
        $vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
        $vendor = $vendor_repo->findByUserId((int) $user_id);
    } catch (\Throwable $e) {
        // سجّل في السجل إن رغبت: error_log('VMP: vendor lookup failed: ' . $e->getMessage());
        $vendor = null;
    }
}

/* ====== رابط المتجر العام (عرض المتجر في نافذة جديدة) ====== */
$store_url = '';
if ($vendor && !empty($vendor->store_slug) && $vendor->status === 'approved') {
    // نستخدم sanitize_title أو rawurlencode (حافظ على سلامة المسار)
    $slug = sanitize_title($vendor->store_slug);
    $store_url = home_url('/store/' . rawurlencode($slug) . '/');
    $store_url = esc_url($store_url);
}
?>
<div class="vmp-nav">
    <a href="<?php echo esc_url(add_query_arg(['vmp_page' => 'dashboard'], $dashboard_url)); ?>"
       class="<?php echo esc_attr($current_page === 'dashboard' ? 'active' : ''); ?>">
        <!-- أيقونة -->
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        <span><?php _e('الرئيسية', 'vmp'); ?></span>
    </a>

    <a href="<?php echo esc_url(add_query_arg(['vmp_page' => 'products'], $dashboard_url)); ?>"
       class="<?php echo esc_attr(in_array($current_page, ['products', 'add-product', 'ai-create-product'], true) ? 'active' : ''); ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
        <span><?php _e('المنتجات', 'vmp'); ?></span>
    </a>

    <a href="<?php echo esc_url(add_query_arg(['vmp_page' => 'orders'], $dashboard_url)); ?>"
       class="<?php echo esc_attr($current_page === 'orders' ? 'active' : ''); ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
        <span><?php _e('الطلبات', 'vmp'); ?></span>
    </a>

    <a href="<?php echo esc_url(add_query_arg(['vmp_page' => 'withdrawals'], $dashboard_url)); ?>"
       class="<?php echo esc_attr($current_page === 'withdrawals' ? 'active' : ''); ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span><?php _e('سحب الأرباح', 'vmp'); ?></span>
    </a>

    <a href="<?php echo esc_url(add_query_arg(['vmp_page' => 'subscriptions'], $dashboard_url)); ?>"
       class="<?php echo esc_attr($current_page === 'subscriptions' ? 'active' : ''); ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span><?php _e('الاشتراك', 'vmp'); ?></span>
    </a>

    <?php if ($vendor && $vendor->status === 'approved'): ?>
        <a href="<?php echo esc_url(add_query_arg(['vmp_page' => 'ai-create-product'], $dashboard_url)); ?>"
           class="<?php echo esc_attr($current_page === 'ai-create-product' ? 'active' : ''); ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
            <span><?php _e('إنشاء بالذكاء الاصطناعي', 'vmp'); ?></span>
        </a>
    <?php endif; ?>

    <a href="<?php echo esc_url(add_query_arg(['vmp_page' => 'profile'], $dashboard_url)); ?>"
       class="<?php echo esc_attr($current_page === 'profile' ? 'active' : ''); ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        <span><?php _e('الإعدادات', 'vmp'); ?></span>
    </a>

    <?php if ($vendor && $vendor->status === 'approved' && $store_url): ?>
        <a href="<?php echo esc_url($store_url); ?>" target="_blank" rel="noopener noreferrer"
           style="margin-right: auto; color: var(--vmp-primary); background: var(--vmp-primary-light);">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            <span><?php _e('عرض المتجر', 'vmp'); ?></span>
        </a>
    <?php endif; ?>
</div>