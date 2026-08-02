<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\ProductRepository;
use VMP\Repositories\VendorRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وحدة واتساب – تدير زر واتساب للبائعين، تتبع النقرات، وإحصائيات متقدمة للمشرف
 */
class Whatsapp extends AbstractModule
{
    private VendorRepository $vendorRepository;
    private string $clicks_table;

    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        global $wpdb;
        parent::__construct($container);
        $this->vendorRepository = $this->make(VendorRepository::class);
        $this->clicks_table = $wpdb->prefix . 'vmp_whatsapp_clicks';
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        // ── عرض زر واتساب في صفحة المنتج الفردي (يبقى هنا لأنه Hook وليس AJAX) ──
        $show_on_product = get_option('vmp_whatsapp_show_on_product', true);
        if ($show_on_product) {
            add_action('woocommerce_single_product_summary', [$this, 'render_product_button'], 35);
        }

        // تم نقل جميع مسارات AJAX إلى ActionDispatcher / RouteRegistry
        // add_action('wp_ajax_vmp_track_whatsapp_click', [$this, 'ajax_track_click']);
        // add_action('wp_ajax_nopriv_vmp_track_whatsapp_click', [$this, 'ajax_track_click']);
        // add_action('wp_ajax_vmp_save_whatsapp_settings', [$this, 'ajax_save_settings']);
        // add_action('wp_ajax_vmp_get_whatsapp_stats', [$this, 'ajax_get_stats']);
        // add_action('wp_ajax_vmp_admin_whatsapp_settings', [$this, 'ajax_admin_settings']);
        // add_action('wp_ajax_vmp_admin_get_whatsapp_stats', [$this, 'ajax_admin_get_stats']);
        // add_action('wp_ajax_vmp_admin_get_vendor_whatsapp_stats', [$this, 'ajax_admin_get_vendor_stats']);
        // add_action('wp_ajax_vmp_admin_get_whatsapp_chart', [$this, 'ajax_admin_get_chart']);
    }

    /* ═══════════════════════════════════════════════════════════ */
    /* دوال عرض الأزرار                                           */
    /* ═══════════════════════════════════════════════════════════ */

    /**
     * عرض زر واتساب في صفحة المنتج الفردي
     */
    public function render_product_button(): void
    {
        global $post;
        if (!$post) {
            return;
        }

        $product_repo = $this->make(ProductRepository::class);
        $vendor_product = $product_repo->findByProductId($post->ID);
        if (!$vendor_product) {
            return;
        }

        $vendor = $this->vendorRepository->find((int) $vendor_product->vendor_id);
        if (!$vendor || empty($vendor->whatsapp_number)) {
            return;
        }

        $subscription_module = $this->container->get('module_manager')->get_module('subscription');
        if ($subscription_module && !$subscription_module->has_feature((int) $vendor->id, 'whatsapp_button')) {
            return;
        }

        $product = wc_get_product($post->ID);
        $product_name = $product ? $product->get_name() : '';
        $message = $this->build_message($vendor, $product_name, 'product');
        $url = $this->build_url($vendor->whatsapp_number, $message);

        $this->render_button($url, (int) $vendor->id, $post->ID, 'product');
    }

    /**
     * عرض زر واتساب في صفحة المتجر (للبائع)
     */
    public function render_store_button(int $vendor_id): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        $whatsapp_number = $vendor ? $this->get_vendor_whatsapp_number($vendor) : '';
        if (!$vendor || empty($whatsapp_number)) {
            return;
        }

        $subscription_module = $this->container->get('module_manager')->get_module('subscription');
        if ($subscription_module && !$subscription_module->has_feature($vendor_id, 'whatsapp_button')) {
            return;
        }

        $message = $this->build_message($vendor, '', 'store');
        $url = $this->build_url($whatsapp_number, $message);
        $this->render_button($url, $vendor_id, 0, 'store');
    }

    /**
     * عرض زر واتساب بجانب كل منتج في صفحة المتجر
     */
    public function render_store_product_button(int $vendor_id, object $product): void
    {
        if (!$vendor_id || !$product) {
            return;
        }

        $vendor = $this->vendorRepository->find($vendor_id);
        $whatsapp_number = $vendor ? $this->get_vendor_whatsapp_number($vendor) : '';
        if (!$vendor || empty($whatsapp_number)) {
            return;
        }

        $subscription_module = $this->container->get('module_manager')->get_module('subscription');
        if ($subscription_module && !$subscription_module->has_feature($vendor_id, 'whatsapp_button')) {
            return;
        }

        $product_name = method_exists($product, 'get_name') ? $product->get_name() : '';
        $message = $this->build_message($vendor, $product_name, 'product');
        $url = $this->build_url($whatsapp_number, $message);

        $this->render_button($url, $vendor_id, $product->get_id(), 'product_request', __('طلب المنتج عبر واتساب', 'vmp'));
    }

    /* ═══════════════════════════════════════════════════════════ */
    /* دوال مساعدة لبناء الأزرار                                  */
    /* ═══════════════════════════════════════════════════════════ */

    /**
     * الحصول على رقم واتساب من البائع
     */
    private function get_vendor_whatsapp_number(object $vendor): string
    {
        $number = trim((string) ($vendor->whatsapp_number ?? ''));
        if ($number !== '') {
            return $number;
        }
        return trim((string) ($vendor->store_phone ?? ''));
    }

    /**
     * بناء رسالة واتساب
     */
    private function build_message(object $vendor, string $context = '', string $type = 'product'): string
    {
        $custom_message = $vendor->whatsapp_message ?? '';

        if (!empty($custom_message)) {
            return str_replace(
                ['{store_name}', '{product_name}', '{site_name}'],
                [$vendor->store_name, $context, get_bloginfo('name')],
                $custom_message
            );
        }

        if ($type === 'product' && !empty($context)) {
            return sprintf(
                __('مرحباً، أريد الاستفسار عن منتج "%s" من متجر %s', 'vmp'),
                $context,
                $vendor->store_name
            );
        }

        return sprintf(
            __('مرحباً، أريد الاستفسار من متجر %s', 'vmp'),
            $vendor->store_name
        );
    }

    /**
     * بناء رابط واتساب
     */
    private function build_url(string $number, string $message): string
    {
        $clean_number = preg_replace('/[^0-9+]/', '', $number);
        $clean_number = ltrim($clean_number, '+');

        return 'https://wa.me/' . $clean_number . '?text=' . rawurlencode($message);
    }

    /**
     * عرض زر واتساب (HTML)
     */
    private function render_button(string $url, int $vendor_id, int $product_id, string $type, string $button_text = ''): void
    {
        $nonce = wp_create_nonce('vmp_public_nonce');
        $current_url = esc_url(home_url(add_query_arg(null, null)));
        if (empty($button_text)) {
            $button_text = __('تواصل عبر واتساب', 'vmp');
        }
        ?>
        <div class="vmp-whatsapp-wrap">
            <a href="<?php echo esc_url($url); ?>"
               class="vmp-whatsapp-btn vmp-wa-track"
               target="_blank"
               rel="noopener noreferrer nofollow"
               id="vmp-wa-btn-<?php echo $vendor_id; ?>"
               data-vendor-id="<?php echo esc_attr($vendor_id); ?>"
               data-product-id="<?php echo esc_attr($product_id); ?>"
               data-click-type="<?php echo esc_attr($type); ?>"
               data-nonce="<?php echo esc_attr($nonce); ?>"
               data-page-url="<?php echo esc_attr($current_url); ?>"
               aria-label="<?php echo esc_attr($button_text); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="vmp-wa-icon" aria-hidden="true">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                <span><?php echo esc_html($button_text); ?></span>
            </a>
        </div>
        <?php
    }

    /* ═══════════════════════════════════════════════════════════ */
    /* أكشنات AJAX للبائع (تتبع، إعدادات، إحصائيات)              */
    /* ═══════════════════════════════════════════════════════════ */

    /**
     * ✅ تتبع نقرات واتساب عبر AJAX
     */
    public function ajax_track_click(): void
    {
        if (!check_ajax_referer('vmp_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        global $wpdb;

        $vendor_id = (int) ($_POST['vendor_id'] ?? 0);
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $click_type = sanitize_text_field($_POST['click_type'] ?? 'button');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');
        $page_url = strtok($page_url, '?');

        $user_agent = sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255));
        $referrer = esc_url_raw(substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500));

        $wpdb->insert($this->clicks_table, [
            'vendor_id' => $vendor_id,
            'product_id' => $product_id ?: null,
            'page_url' => $page_url,
            'click_type' => $click_type,
            'user_agent' => $user_agent,
            'referrer' => $referrer,
            'clicked_at' => current_time('mysql'),
        ]);

        wp_send_json_success();
    }

    /**
     * حفظ إعدادات واتساب للبائع
     */
    public function ajax_save_settings(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $subscription_module = $this->container->get('module_manager')->get_module('subscription');
        if ($subscription_module && !$subscription_module->has_feature((int) $vendor->id, 'whatsapp_button')) {
            wp_send_json_error(['message' => __('هذه الميزة غير متاحة في خطتك الحالية', 'vmp')]);
        }

        $whatsapp_number = sanitize_text_field($_POST['whatsapp_number'] ?? '');
        $whatsapp_message = sanitize_textarea_field($_POST['whatsapp_message'] ?? '');

        if (!empty($whatsapp_number) && !preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/\s/', '', $whatsapp_number))) {
            wp_send_json_error(['message' => __('رقم الواتساب غير صالح', 'vmp')]);
        }

        if ($this->vendorRepository->update((int) $vendor->id, [
            'whatsapp_number' => $whatsapp_number,
            'whatsapp_message' => $whatsapp_message,
        ])) {
            wp_send_json_success(['message' => __('تم حفظ إعدادات واتساب', 'vmp')]);
        }

        wp_send_json_error(['message' => __('لم يتم تغيير أي بيانات', 'vmp')]);
    }

    /**
     * جلب إحصائيات واتساب للبائع (خاصته)
     */
    public function ajax_get_stats(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        global $wpdb;
        $vendor_id = (int) $vendor->id;

        $total_clicks = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->clicks_table} WHERE vendor_id = %d", $vendor_id)
        );
        $today_clicks = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->clicks_table} WHERE vendor_id = %d AND DATE(clicked_at) = CURDATE()",
                $vendor_id
            )
        );
        $monthly_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(clicked_at, '%%Y-%%m-%%d') as day, COUNT(*) as clicks
                 FROM {$this->clicks_table}
                 WHERE vendor_id = %d AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE_FORMAT(clicked_at, '%%Y-%%m-%%d')
                 ORDER BY day ASC",
                $vendor_id
            )
        );

        wp_send_json_success([
            'total_clicks' => $total_clicks,
            'today_clicks' => $today_clicks,
            'monthly_data' => $monthly_data,
        ]);
    }

    /* ═══════════════════════════════════════════════════════════ */
    /* أكشنات المشرف (إعدادات عامة + إحصائيات متقدمة)            */
    /* ═══════════════════════════════════════════════════════════ */

    /**
     * إعدادات واتساب في لوحة المشرف (عامة)
     */
    public function ajax_admin_settings(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');

        if (!current_user_can('vmp_manage_settings')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $settings = [
            'show_on_product' => !empty($_POST['show_on_product']),
            'show_on_store' => !empty($_POST['show_on_store']),
            'show_on_catalog' => !empty($_POST['show_on_catalog']),
            'button_text' => sanitize_text_field($_POST['button_text'] ?? __('تواصل عبر واتساب', 'vmp')),
            'button_color' => sanitize_hex_color($_POST['button_color'] ?? '#25D366'),
        ];

        foreach ($settings as $key => $value) {
            update_option("vmp_whatsapp_{$key}", $value);
        }

        wp_send_json_success(['message' => __('تم حفظ إعدادات واتساب', 'vmp')]);
    }

    /**
     * ✅ إحصائيات واتساب لجميع البائعين (للمشرف)
     */
    public function ajax_admin_get_stats(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');

        if (!current_user_can('vmp_manage_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        global $wpdb;

        $vendor_stats = $wpdb->get_results("
            SELECT 
                v.id,
                v.store_name,
                v.store_slug,
                v.user_id,
                COALESCE(COUNT(c.id), 0) AS total_clicks,
                COALESCE(SUM(CASE WHEN DATE(c.clicked_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS today_clicks,
                COALESCE(SUM(CASE WHEN c.clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS week_clicks,
                COALESCE(SUM(CASE WHEN c.clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS month_clicks,
                COALESCE(SUM(CASE WHEN c.click_type = 'product' THEN 1 ELSE 0 END), 0) AS product_clicks,
                COALESCE(SUM(CASE WHEN c.click_type = 'store' THEN 1 ELSE 0 END), 0) AS store_clicks
            FROM {$wpdb->prefix}vmp_vendors v
            LEFT JOIN {$this->clicks_table} c ON v.id = c.vendor_id
            GROUP BY v.id
            ORDER BY total_clicks DESC
        ");

        wp_send_json_success([
            'vendors' => $vendor_stats,
        ]);
    }

    /**
     * ✅ إحصائيات واتساب لبائع معين (للمشرف)
     */
    public function ajax_admin_get_vendor_stats(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');

        if (!current_user_can('vmp_manage_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $vendor_id = (int) ($_POST['vendor_id'] ?? 0);
        if ($vendor_id <= 0) {
            wp_send_json_error(['message' => __('معرف البائع غير صالح', 'vmp')]);
        }

        global $wpdb;

        // ── إحصائيات تفصيلية للبائع ──
        $stats = $wpdb->get_row(
            $wpdb->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) AS total_clicks,
                    COALESCE(SUM(CASE WHEN DATE(clicked_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS today_clicks,
                    COALESCE(SUM(CASE WHEN clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS week_clicks,
                    COALESCE(SUM(CASE WHEN clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS month_clicks,
                    COALESCE(SUM(CASE WHEN click_type = 'product' THEN 1 ELSE 0 END), 0) AS product_clicks,
                    COALESCE(SUM(CASE WHEN click_type = 'store' THEN 1 ELSE 0 END), 0) AS store_clicks
                FROM {$this->clicks_table}
                WHERE vendor_id = %d
            ", $vendor_id)
        );

        // ── المنتجات الأكثر استفساراً ──
        $top_products = $wpdb->get_results(
            $wpdb->prepare("
                SELECT 
                    product_id,
                    COUNT(*) AS clicks,
                    (SELECT post_title FROM {$wpdb->posts} WHERE ID = product_id) AS product_name
                FROM {$this->clicks_table}
                WHERE vendor_id = %d AND product_id > 0
                GROUP BY product_id
                ORDER BY clicks DESC
                LIMIT 10
            ", $vendor_id)
        );

        // ── النقرات اليومية (آخر 30 يوم) ──
        $daily = $wpdb->get_results(
            $wpdb->prepare("
                SELECT 
                    DATE(clicked_at) AS date,
                    COUNT(*) AS clicks
                FROM {$this->clicks_table}
                WHERE vendor_id = %d AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(clicked_at)
                ORDER BY date ASC
            ", $vendor_id)
        );

        wp_send_json_success([
            'stats' => $stats,
            'top_products' => $top_products,
            'daily' => $daily,
        ]);
    }

    /**
     * ✅ بيانات الرسم البياني لواتساب (للمشرف)
     */
    public function ajax_admin_get_chart(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');

        if (!current_user_can('vmp_manage_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        global $wpdb;

        $vendor_id = (int) ($_POST['vendor_id'] ?? 0);
        $months = (int) ($_POST['months'] ?? 6);

        if ($vendor_id > 0) {
            // بيانات بائع معين
            $data = $wpdb->get_results(
                $wpdb->prepare("
                    SELECT 
                        DATE_FORMAT(clicked_at, '%%Y-%%m-%%d') AS date,
                        COUNT(*) AS clicks
                    FROM {$this->clicks_table}
                    WHERE vendor_id = %d AND clicked_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
                    GROUP BY DATE(clicked_at)
                    ORDER BY date ASC
                ", $vendor_id, $months)
            );
        } else {
            // بيانات جميع البائعين
            $data = $wpdb->get_results(
                $wpdb->prepare("
                    SELECT 
                        DATE_FORMAT(clicked_at, '%%Y-%%m-%%d') AS date,
                        COUNT(*) AS clicks
                    FROM {$this->clicks_table}
                    WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
                    GROUP BY DATE(clicked_at)
                    ORDER BY date ASC
                ", $months)
            );
        }

        wp_send_json_success(['data' => $data]);
    }
}

// ✅ تعريف الـ alias للتوافق مع الإصدارات القديمة
if (!class_exists('VMP\\Modules\\WhatsApp', false)) {
    class_alias(Whatsapp::class, 'VMP\\Modules\\WhatsApp');
}