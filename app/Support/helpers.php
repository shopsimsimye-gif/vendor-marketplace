<?php

namespace VMP\Support {
    defined('ABSPATH') || exit;

    use VMP\Core\Application;
    use VMP\Core\Container;
    use VMP\Support\Config;
    use VMP\Repositories\SubscriptionRepository;

    /**
     * الحصول على قيمة إعداد من ملفات الإعدادات
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config;
        if ($config === null) {
            $config = Config::getInstance(VMP_PLUGIN_DIR . 'app/Config');
        }
        return $config->get($key, $default);
    }

    /**
     * الحصول على كائن التطبيق
     */
    function app(): ?Application
    {
        $container = Container::getInstance();
        $instance = $container->make('app');
        return $instance instanceof Application ? $instance : null;
    }

    // ─────────────────────────────────────────────────────────────
    // دوال مساعدة للبائع (Vendor Helpers) - تم دمجها من الملف الثاني
    // ─────────────────────────────────────────────────────────────

    /**
     * الحصول على معرف البائع للمستخدم الحالي
     */
    function get_current_vendor_id(): int
    {
        // أولاً، تأكد من وجود جلسة مستخدم
        $user_id = get_current_user_id();
        if (!$user_id) {
            return 0;
        }

        // 1) Try user meta first (set during registration/login flows)
        $meta_vendor_id = (int) get_user_meta($user_id, 'vmp_vendor_id', true);
        if ($meta_vendor_id > 0) {
            return $meta_vendor_id;
        }

        // 2) Fallback to DB lookup
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        return (int) ($id ?? 0);
    }

    /**
     * الحصول على بيانات البائع بواسطة معرف البائع
     */
    function get_vendor(int $vendor_id): ?object
    {
        global $wpdb;
        if (!$vendor_id) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vmp_vendors WHERE id = %d LIMIT 1",
            $vendor_id
        )) ?: null;
    }

    /**
     * هل المستخدم الحالي بائع مسجّل ومعتمد؟
     */
    function is_vendor(int $user_id = 0): bool
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        if (!$user_id) {
            return false;
        }

        $user = wp_get_current_user();
        if (!in_array('vmp_vendor', (array) $user->roles)) {
            return false;
        }

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        return $status === 'approved';
    }

    /**
     * إعادة توجيه المستخدم إذا لم يكن بائعاً مسجّلاً، أو إذا كان حسابه معلقاً
     * استخدم هذا في أعلى قوالب لوحة تحكم البائع
     */
    function require_vendor(bool $require_approved = false): void
    {
        if (!is_user_logged_in()) {
            // تسجيل تشخيصي لمشكلة الجلسة إذا كانت تحدث
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP][AUTH] require_vendor: user not logged in. cookies=' . json_encode(array_keys($_COOKIE ?? [])));
            }

            $current_url = home_url($_SERVER['REQUEST_URI'] ?? '/');
            wp_redirect(wp_login_url($current_url));
            exit;
        }

        $vendor_id = get_current_vendor_id();
        if (!$vendor_id) {
            // تسجيل سبب الرفض للمساعدة في التشخيص
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $meta_vid = (int) get_user_meta(get_current_user_id(), 'vmp_vendor_id', true);
                error_log('[VMP][AUTH] require_vendor: vendor not found for user ' . get_current_user_id() . '. meta_vmp_vendor_id=' . $meta_vid);
            }

            $settings = get_option('vmp_settings', []);
            $register_page_id = !empty($settings['display']['register_page']) ? (int) $settings['display']['register_page'] : 0;
            $register_page = $register_page_id && get_post($register_page_id)
                ? get_permalink($register_page_id)
                : home_url('/vendor-register/');
            wp_redirect($register_page);
            exit;
        }

        if ($require_approved) {
            $vendor = get_vendor($vendor_id);
            if ($vendor && $vendor->status !== 'approved') {
                // لا تعيد التوجيه — فقط نعرض تنبيهاً داخل القالب
            }
        }
    }

    /**
     * الحصول على رابط لوحة تحكم البائع
     */
    function dashboard_url(string $page = ''): string
    {
        $settings = get_option('vmp_settings', []);
        $dashboard_page_id = !empty($settings['display']['dashboard_page']) ? (int) $settings['display']['dashboard_page'] : 0;
        $dashboard_page = $dashboard_page_id && get_post($dashboard_page_id)
            ? get_permalink($dashboard_page_id)
            : home_url('/vendor-dashboard/');

        if ($page) {
            return add_query_arg('vmp_page', $page, $dashboard_page);
        }
        return $dashboard_page;
    }

    /**
     * الحصول على معرف البائع عبر معرف المنتج
     */
    function get_product_vendor_id(int $product_id): int
    {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT vendor_id FROM {$wpdb->prefix}vmp_vendor_products WHERE product_id = %d LIMIT 1",
            $product_id
        ));
        return (int) ($id ?? 0);
    }

    /**
     * تنسيق المبلغ المالي بعملة الموقع
     */
    function price(float $amount): string
    {
        if (function_exists('wc_price')) {
            $formatted = wc_price($amount);
            // إزالة الكسور العشرية .00 للعملات التي لا تحتاجها (مثل SAR)
            $currency = get_woocommerce_currency();
            if (in_array($currency, ['SAR', 'BHD', 'KWD', 'OMR', 'JOD', 'IQD'], true)) {
                $formatted = preg_replace('/\.00$/', '', $formatted);
            }
            return $formatted;
        }
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'SAR';
        $decimals = in_array($currency, ['SAR', 'BHD', 'KWD', 'OMR', 'JOD', 'IQD'], true) ? 0 : 2;
        return number_format($amount, $decimals) . ' ' . $currency;
    }

    /**
     * هل يمكن للمستخدم الحالي (البائع) إضافة منتج جديد؟
     */
    function can_add_product(int $vendor_id = 0): bool
    {
        if (!$vendor_id) {
            $vendor_id = get_current_vendor_id();
        }
        if (!$vendor_id) {
            return false;
        }

        try {
            $sub_repo = Container::getInstance()->make(SubscriptionRepository::class);
            return $sub_repo->canAddProduct($vendor_id);
        } catch (\Exception $e) {
            // في حال فشل الحصول على المستودع، نستخدم fallback
            global $wpdb;
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}vmp_vendor_products WHERE vendor_id = %d",
                $vendor_id
            ));
            return $count < 10;
        }
    }
}

// ─────────────────────────────────────────────────────────────
// تسجيل الدوال في النطاق العام (Global Namespace)
// ─────────────────────────────────────────────────────────────
namespace {
    if (!function_exists('config')) {
        function config(string $key, mixed $default = null): mixed
        {
            return \VMP\Support\config($key, $default);
        }
    }

    if (!function_exists('app')) {
        function app(): ?\VMP\Core\Application
        {
            return \VMP\Support\app();
        }
    }

    // ── دوال البائع المساعدة (Global Wrappers) ──
    if (!function_exists('vmp_get_current_vendor_id')) {
        function vmp_get_current_vendor_id(): int
        {
            return \VMP\Support\get_current_vendor_id();
        }
    }

    if (!function_exists('vmp_get_vendor')) {
        function vmp_get_vendor(int $vendor_id): ?object
        {
            return \VMP\Support\get_vendor($vendor_id);
        }
    }

    if (!function_exists('vmp_is_vendor')) {
        function vmp_is_vendor(int $user_id = 0): bool
        {
            return \VMP\Support\is_vendor($user_id);
        }
    }

    if (!function_exists('vmp_require_vendor')) {
        function vmp_require_vendor(bool $require_approved = false): void
        {
            \VMP\Support\require_vendor($require_approved);
        }
    }

    if (!function_exists('vmp_dashboard_url')) {
        function vmp_dashboard_url(string $page = ''): string
        {
            return \VMP\Support\dashboard_url($page);
        }
    }

    if (!function_exists('vmp_get_product_vendor_id')) {
        function vmp_get_product_vendor_id(int $product_id): int
        {
            return \VMP\Support\get_product_vendor_id($product_id);
        }
    }

    if (!function_exists('vmp_price')) {
        function vmp_price(float $amount): string
        {
            return \VMP\Support\price($amount);
        }
    }

    if (!function_exists('vmp_can_add_product')) {
        function vmp_can_add_product(int $vendor_id = 0): bool
        {
            return \VMP\Support\can_add_product($vendor_id);
        }
    }

    /**
     * تنسيق السعر للعملة المحلية (wrapper لـ vmp_price)
     */
    if (!function_exists('vmp_format_price')) {
        function vmp_format_price(float $amount): string
        {
            return \VMP\Support\price($amount);
        }
    }

    /**
     * التحقق من توفر رابط متجر (slug)
     */
    if (!function_exists('vmp_check_store_slug')) {
        function vmp_check_store_slug(string $slug): bool
        {
            global $wpdb;
            $slug = sanitize_title($slug);
            if (!$slug) {
                return false;
            }
            return (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$wpdb->prefix}vmp_vendors WHERE slug = %s LIMIT 1",
                $slug
            ));
        }
    }

    /**
     * حفظ بيانات نموذج التسجيل في الجلسة
     */
    if (!function_exists('vmp_save_register_data')) {
        function vmp_save_register_data(array $data, int $step): void
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['vmp_vendor_register_data'] = $data;
            $_SESSION['vmp_vendor_register_step'] = $step;
        }
    }

    /**
     * استرجاع بيانات نموذج التسجيل من الجلسة
     */
    if (!function_exists('vmp_get_register_data')) {
        function vmp_get_register_data(): array
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            return $_SESSION['vmp_vendor_register_data'] ?? [];
        }
    }

    /**
     * استرجاع الخطوة الحالية من الجلسة
     */
    if (!function_exists('vmp_get_register_step')) {
        function vmp_get_register_step(): int
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            return (int) ($_SESSION['vmp_vendor_register_step'] ?? 1);
        }
    }

    /**
     * مسح بيانات التسجيل من الجلسة
     */
    if (!function_exists('vmp_clear_register_data')) {
        function vmp_clear_register_data(): void
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            unset($_SESSION['vmp_vendor_register_data']);
            unset($_SESSION['vmp_vendor_register_step']);
            unset($_SESSION['vmp_vendor_register_errors']);
        }
    }
}
