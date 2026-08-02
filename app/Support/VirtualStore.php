<?php
namespace VMP\Support;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Repositories\VendorRepository;

/**
 * Class VirtualStore
 *
 * Description of administrative platform component VirtualStore.
 *
 * @package vendor-marketplace
 */
class VirtualStore
{
    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public static function init(): void
    {
        // تسجيل الفلاتر لاستبعاد المنشورات الوهمية من جميع الاستعلامات
        self::registerDummyPostExclusion();

        // منع ووكومرس من إنشاء بيانات عميل وهمية للبائعين
        self::registerWooCommerceCustomerPrevention();

        global $wp_query;

        $vendor_slug = self::resolveVendorSlugFromRequest($_SERVER, $_GET);
        if (empty($vendor_slug) && function_exists('get_query_var')) {
            $vendor_slug = sanitize_text_field((string) get_query_var('vendor_store', ''));
        }
        if (empty($vendor_slug) && isset($wp_query->query_vars['vendor_store'])) {
            $vendor_slug = sanitize_text_field((string) $wp_query->query_vars['vendor_store']);
        }
        if (empty($vendor_slug)) {
            return;
        }

        $vendor_repo = Container::getInstance()->make(VendorRepository::class);
        $vendor = $vendor_repo->findBySlug($vendor_slug);

        if (!$vendor || $vendor->status !== 'approved') {
            self::handle404();
            return;
        }

        self::setupVirtualPage($vendor, $vendor_slug);
    }

    /**
     * Resolve the vendor slug from the current request.
     *
     * @param array<string, mixed> $server Server globals.
     * @param array<string, mixed> $get GET globals.
     * @return string
     */
    public static function resolveVendorSlugFromRequest(array $server, array $get): string
    {
        $slug = '';

        foreach (['REQUEST_URI', 'PATH_INFO', 'ORIG_PATH_INFO', 'REDIRECT_URL', 'REDIRECT_SCRIPT_URL', 'REQUEST_URL'] as $key) {
            if (empty($server[$key])) {
                continue;
            }

            $uri = trim((string) $server[$key]);
            if ($uri === '') {
                continue;
            }

            $path = $uri;
            if (function_exists('wp_parse_url')) {
                $path = wp_parse_url($uri, PHP_URL_PATH) ?: $uri;
            } elseif (function_exists('parse_url')) {
                $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
            }

            $path = trim((string) $path, '/');
            if (preg_match('#^store/([^/]+)(?:/.*)?$#', $path, $matches)) {
                $slug = sanitize_text_field($matches[1]);
                break;
            }
        }

        if (empty($slug) && !empty($get['vendor_store'])) {
            $slug = sanitize_text_field((string) $get['vendor_store']);
        }

        return $slug;
    }

    /**
     * تسجيل فلتر لاستبعاد المنشورات الوهمية (ID -999 و 0) من الاستعلامات
     * يمنع ظهور البيانات الوهمية في إعدادات المشرف، خرائط الموقع، وأدوات SEO
     */
    private static function registerDummyPostExclusion(): void
    {
        // استبعاد من الاستعلامات الرئيسية في الواجهة الأمامية
        add_filter('pre_get_posts', function (\WP_Query $query) {
            // لا نتدخل في استعلامات لوحة التحكم أو الاستعلامات الفرعية
            if (is_admin() || !$query->is_main_query()) {
                return;
            }

            $excluded_ids = [-999, 0];
            $current_excluded = $query->get('post__not_in', []);
            
            // دمج المعرفات المستبعدة مع الموجودة مسبقاً
            $query->set('post__not_in', array_merge($current_excluded, $excluded_ids));
        }, 20);

        // استبعاد من خرائط الموقع (sitemaps) - WordPress 5.5+
        add_filter('wp_sitemaps_posts_query_args', function (array $args) {
            $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], [-999, 0]);
            return $args;
        });

        // استبعاد من استعلامات الصفحات في لوحة التحكم (تنظيف قوائم الصفحات)
        add_filter('parse_query', function (\WP_Query $query) {
            if (!is_admin()) {
                return;
            }

            // فقط للاستعلامات التي تطلب نوع 'page'
            if (isset($query->query['post_type']) && $query->query['post_type'] === 'page') {
                $excluded_ids = [-999, 0];
                $current_excluded = $query->get('post__not_in', []);
                $query->set('post__not_in', array_merge($current_excluded, $excluded_ids));
            }
        }, 20);
    }

    /**
     * منع ووكومرس من إنشاء بيانات عميل وهمية عند تسجيل البائعين
     * أو تنظيف البيانات الوهمية بعد الإنشاء
     */
    private static function registerWooCommerceCustomerPrevention(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // فلتر لتعديل بيانات العميل الجديد - إزالة حقول الفوترة/الشحن الافتراضية الفارغة
        add_filter('woocommerce_new_customer_data', function (array $new_customer_data) {
            // إذا كان المستخدم له دور بائع، لا ننشئ بيانات عميل وهمية
            $user_id = $new_customer_data['user_id'] ?? 0;
            if ($user_id) {
                $user = get_userdata($user_id);
                if ($user && in_array('vmp_vendor', (array) $user->roles, true)) {
                    // إرجاع مصفوفة فارغة لمنع إنشاء بيانات عميل
                    return [];
                }
            }
            return $new_customer_data;
        }, 10, 1);

        // إجراء بعد إنشاء العميل - تنظيف البيانات الوهمية للبائعين
        add_action('woocommerce_created_customer', function (int $customer_id, array $new_customer_data, bool $password_generated) {
            $user = get_userdata($customer_id);
            if ($user && in_array('vmp_vendor', (array) $user->roles, true)) {
                // حذف بيانات الفوترة والشحن الافتراضية الفارغة
                $billing_fields = [
                    'billing_first_name', 'billing_last_name', 'billing_company',
                    'billing_address_1', 'billing_address_2', 'billing_city',
                    'billing_postcode', 'billing_country', 'billing_state',
                    'billing_email', 'billing_phone',
                ];
                $shipping_fields = [
                    'shipping_first_name', 'shipping_last_name', 'shipping_company',
                    'shipping_address_1', 'shipping_address_2', 'shipping_city',
                    'shipping_postcode', 'shipping_country', 'shipping_state',
                ];

                foreach (array_merge($billing_fields, $shipping_fields) as $field) {
                    delete_user_meta($customer_id, $field);
                }
            }
        }, 10, 3);
    }

    /**
     * SetupVirtualPage functionality helper.
     *
     * @param object $vendor Description index.
     * @param string $slug Description index.
     * @return void Output payload.
     */
    private static function setupVirtualPage(object $vendor, string $slug): void
    {
        global $wp_query, $post;

        $dummy_post = new \stdClass();
        $dummy_post->ID = -999;
        $dummy_post->post_author = 1;
        $dummy_post->post_date = current_time('mysql');
        $dummy_post->post_date_gmt = current_time('mysql', 1);
        $dummy_post->post_content = '[vmp_vendor_store slug="' . esc_attr($slug) . '"]';
        $dummy_post->post_title = sprintf(__('متجر %s', 'vmp'), $vendor->store_name);
        $dummy_post->post_status = 'publish';
        $dummy_post->comment_status = 'closed';
        $dummy_post->ping_status = 'closed';
        $dummy_post->post_name = 'vendor-store-' . $slug;
        $dummy_post->post_type = 'page';
        $dummy_post->filter = 'raw';

        $wp_post = new \WP_Post($dummy_post);

        // تعيين جميع متغيرات الاستعلام
        $wp_query->post = $wp_post;
        $wp_query->posts = [$wp_post];
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->queried_object = $wp_post;
        $wp_query->queried_object_id = $wp_post->ID;
        $wp_query->is_page = true;
        $wp_query->is_single = false;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_404 = false;
        $wp_query->max_num_pages = 1;

        $post = $wp_post;
        $GLOBALS['post'] = $wp_post;
        $GLOBALS['wp_the_query'] = $wp_query;

        setup_postdata($wp_post);
        $GLOBALS['vmp_current_vendor'] = $vendor;

        add_filter('the_content', function ($content) use ($slug) {
            if (get_query_var('vendor_store')) {
                return do_shortcode('[vmp_vendor_store slug="' . esc_attr($slug) . '"]');
            }
            return $content;
        }, 10, 1);

        add_action('wp_footer', function () {
            if (get_query_var('vendor_store')) {
                wp_reset_postdata();
            }
        }, 999);
    }

    /**
     * Handle404 functionality helper.
     *
     * @return void Output payload.
     */
    private static function handle404(): void
    {
        global $wp_query, $post;

        $wp_query->set_404();
        status_header(404);

        $dummy_post = new \stdClass();
        $dummy_post->ID = 0;
        $dummy_post->post_type = 'page';
        $dummy_post->post_title = '404';
        $dummy_post->post_status = 'publish';
        $dummy_post->filter = 'raw';

        $wp_post = new \WP_Post($dummy_post);
        $post = $wp_post;
        $GLOBALS['post'] = $wp_post;
        $wp_query->post = $wp_post;
        $wp_query->posts = [$wp_post];
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->queried_object = $wp_post;
        $wp_query->queried_object_id = $wp_post->ID;
        $wp_query->is_404 = true;
        $wp_query->is_page = false;
        $wp_query->is_singular = false;
        $wp_query->max_num_pages = 1;

        setup_postdata($wp_post);
    }
}

namespace VMP;

if (!function_exists('VMP\setup_virtual_store_page')) {
    /**
     * Setup Virtual Store Page functionality helper.
     *
     * @return void Output payload.
     */
    function setup_virtual_store_page(): void
    {
        \VMP\Support\VirtualStore::init();
    }
}
