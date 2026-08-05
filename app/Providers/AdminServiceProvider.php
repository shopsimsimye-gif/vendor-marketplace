<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Admin\VendorRequestsAdminPage;

/**
 * Class AdminServiceProvider
 *
 * مسؤول عن تسجيل قوائم واجهة المشرف (Admin Menu) وإضافة الصفحات الفرعية
 * وتحميل الأصول (CSS/JS) الخاصة بلوحة التحكم.
 *
 * @package vendor-marketplace
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * Boot functionality helper.
     *
     * تسجيل قائمة الإضافة في لوحة تحكم ووردبريس مع جميع الصفحات الفرعية.
     *
     * @return void
     */
    public function boot(): void
    {

        // ── تهيئة صفحة طلبات البائعين (class-based admin page) ──
        // ننشئ instance لتسجيل هوكات admin_menu, admin_enqueue_scripts, wp_ajax
        $this->container->make(VendorRequestsAdminPage::class);

        // ── تشغيل ترحيل قاعدة البيانات تلقائياً عند تحديث الإضافة ──
        add_action('admin_init', static function (): void {
            $saved_version = get_option('vmp_db_version', '0.0.0');
            if (version_compare($saved_version, VMP_VERSION, '<')) {
                \VMP\Core\Install::migrate_existing_tables();
                \VMP\Core\Install::upgrade();
            }
        }, 5);

        // ── قائمة الإضافة الرئيسية ──
        $container = $this->container;
        add_action('admin_menu', static function () use ($container): void {
            // استخدم قدرة احتياطية للمشرف (manage_options) إن كانت متاحة
            $menu_capability = current_user_can('manage_options') ? 'manage_options' : 'vmp_manage_vendors';

            add_menu_page(
                __('Vendor Marketplace', 'vmp'),
                __('Vendor Marketplace', 'vmp'),
                $menu_capability,
                'vmp-dashboard',
                static function (): void {
                    require VMP_PLUGIN_DIR . 'admin/pages/dashboard.php';
                },
                'dashicons-store',
                30
            );

            // ── الصفحات الفرعية ──
            $sub_pages = [
                ['vmp-dashboard',       __('لوحة التحكم', 'vmp'),     __('لوحة التحكم', 'vmp'),     'vmp_manage_vendors',      'dashboard.php'],
                ['vmp-vendors',         __('البائعون', 'vmp'),          __('البائعون', 'vmp'),          'vmp_manage_vendors',      'vendors.php'],
                ['vmp-vendor-requests', __('طلبات البائعين', 'vmp'),    __('طلبات البائعين', 'vmp'),    'manage_vmp_requests',     'requests'],
                ['vmp-products',        __('المنتجات', 'vmp'),          __('المنتجات', 'vmp'),          'vmp_manage_products',     'products.php'],
                ['vmp-orders',          __('الطلبات', 'vmp'),           __('الطلبات', 'vmp'),           'vmp_manage_orders',       'orders.php'],
                ['vmp-commissions',     __('العمولات', 'vmp'),          __('العمولات', 'vmp'),          'vmp_manage_commissions',  'commissions.php'],
                ['vmp-withdrawals',     __('السحوبات', 'vmp'),          __('السحوبات', 'vmp'),          'vmp_manage_withdrawals',  'withdrawals.php'],
                ['vmp-subscriptions',   __('الاشتراكات', 'vmp'),       __('الاشتراكات', 'vmp'),       'vmp_manage_subscriptions','subscriptions.php'],
                ['vmp-ai-settings',     __('إعدادات الذكاء الاصطناعي', 'vmp'), __('الذكاء الاصطناعي', 'vmp'), 'vmp_manage_settings', 'ai-settings.php'],
                ['vmp-settings',        __('الإعدادات', 'vmp'),        __('الإعدادات', 'vmp'),        'vmp_manage_settings',     'settings.php'],
                ['vmp-whatsapp-stats',  __('إحصائيات واتساب', 'vmp'),  __('واتساب', 'vmp'),           'vmp_manage_reports',      'whatsapp-stats.php'],
            ];

            foreach ($sub_pages as $page) {
                $file = $page[4];
                // إذا كان المشرف لديه manage_options فنجعل capability للصفحة هي manage_options
                $capability = current_user_can('manage_options') ? 'manage_options' : $page[3];

                add_submenu_page(
                    'vmp-dashboard',
                    $page[1],
                    $page[2],
                    $capability,
                    $page[0],
                    static function () use ($file, $container): void {
                        if ($file === 'requests') {
                            $requestsPage = $container->make(\VMP\Admin\VendorRequestsAdminPage::class);
                            $requestsPage->renderPage();
                            return;
                        }
                        $path = VMP_PLUGIN_DIR . 'admin/pages/' . $file;
                        if (file_exists($path)) {
                            require $path;
                        } else {
                            echo '<div class="notice notice-error"><p>' . sprintf(__('الملف %s غير موجود.', 'vmp'), esc_html($file)) . '</p></div>';
                        }
                    }
                );
            }
        });

        // ── تحميل الأصول (CSS/JS) في صفحات الإدارة ──
        add_action('admin_enqueue_scripts', static function ($hook): void {
            // تحميل فقط في صفحات VMP
            if (strpos($hook, 'vmp') === false) {
                return;
            }

            // Ensure WP media scripts are available in admin VMP pages (for image picker)
            if (function_exists('wp_enqueue_media')) {
                wp_enqueue_media();
            }

            // ── الأنماط (CSS) ──
            wp_enqueue_style('vmp-admin', VMP_PLUGIN_URL . 'admin/css/admin.css', [], VMP_VERSION);

            // ── السكربتات (JS) ──
            wp_enqueue_script('vmp-admin', VMP_PLUGIN_URL . 'admin/js/admin.js', ['jquery', 'wp-i18n'], VMP_VERSION, true);

            // ── إعدادات الـ JavaScript (vmp_admin object) ──
            wp_localize_script('vmp-admin', 'vmp_admin', [
                'ajax_url'   => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce('vmp_admin_nonce'),
                'plugin_url' => VMP_PLUGIN_URL,
                'strings'    => [
                    'confirm_approve' => __('هل أنت متأكد من الموافقة؟', 'vmp'),
                    'confirm_reject'  => __('هل أنت متأكد من الرفض؟', 'vmp'),
                    'confirm_delete'  => __('هل أنت متأكد من الحذف؟', 'vmp'),
                    'loading'         => __('جاري التحميل...', 'vmp'),
                    'error'           => __('حدث خطأ، يرجى المحاولة مرة أخرى.', 'vmp'),
                ],
            ]);

            // ── تحميل Chart.js في صفحات التقارير ولوحة التحكم ──
            if (strpos($hook, 'vmp-dashboard') !== false || strpos($hook, 'vmp-reports') !== false) {
                wp_enqueue_script('chart-js', VMP_PLUGIN_URL . 'assets/js/chart.min.js', [], '4.4.0', true);
            }
        });
    }
}
