<?php
namespace VMP\Modules\Vendor;

defined('ABSPATH') || exit;

use VMP\Modules\AbstractModule;
use VMP\Repositories\SubscriptionPlanRepository;
use VMP\Repositories\SubscriptionRepository;
use VMP\Repositories\VendorRepository;
use WP_User;

/**
 * مدير هوكات البائع – جميع طلبات AJAX للتسجيل، الملف الشخصي، الموافقة والرفض
 * ✅ تحسين معالجة صلاحيات المشرف والتحقق من nonce
 * ✅ إصلاح مشكلة الموافقة على البائع الجديد
 */
class VendorHooks extends AbstractModule
{
    /**
     * تسجيل جميع إجراءات AJAX والهوكات
     */
    public function register(): void
    {
        // ── إجراءات AJAX (تم نقلها إلى ActionDispatcher / RouteRegistry) ──
        // add_action('wp_ajax_vmp_vendor_register', [$this, 'ajax_register']);
        // add_action('wp_ajax_nopriv_vmp_vendor_register', [$this, 'ajax_register']);
        // add_action('wp_ajax_vmp_vendor_update_profile', [$this, 'ajax_update_profile']);
        // add_action('wp_ajax_vmp_admin_approve_vendor', [$this, 'ajax_admin_approve']);
        // add_action('wp_ajax_vmp_admin_reject_vendor', [$this, 'ajax_admin_reject']);

        // ── إعادة توجيه البائع بعد تسجيل الدخول ──
        add_filter('login_redirect', [$this, 'login_redirect'], 10, 3);
        add_action('wp_login', [$this, 'after_login_redirect'], 10, 2);
        add_filter('woocommerce_login_redirect', [$this, 'woocommerce_login_redirect'], 10, 2);

        // ── منع البائعين من الوصول إلى لوحة تحكم ووردبريس ──
        add_action('admin_init', [$this, 'redirect_vendor_from_admin']);

        // ── إخفاء شريط الأدمن للبائعين ──
        add_action('admin_bar_menu', [$this, 'remove_admin_bar_nodes'], 999);
    }

    /**
     * اختصار للحصول على مستودع البائعين
     */
    private function vendors(): VendorRepository
    {
        return $this->make(VendorRepository::class);
    }

    /* ═══════════════════════════════════════════════════════════ */
    /* دوال إعادة التوجيه وإدارة الوصول                           */
    /* ═══════════════════════════════════════════════════════════ */

    /**
     * Login Redirect functionality helper.
     *
     * @param mixed $redirect_to Description index.
     * @param mixed $requested_redirect_to Description index.
     * @param mixed $user Description index.
     * @return string Output payload.
     */
    public function login_redirect($redirect_to, $requested_redirect_to, $user): string
    {
        if (!is_a($user, 'WP_User')) {
            return $redirect_to;
        }

        if (in_array('vmp_vendor', (array) $user->roles) || user_can($user, 'vmp_vendor')) {
            $dashboard_url = $this->getVendorDashboardUrl();

            $is_default_or_admin = empty($requested_redirect_to) 
                || $requested_redirect_to === admin_url() 
                || strpos($requested_redirect_to, 'wp-admin') !== false
                || strpos($requested_redirect_to, 'my-account') !== false
                || strpos($requested_redirect_to, 'account') !== false;

            if ($is_default_or_admin) {
                return $dashboard_url;
            }

            return $requested_redirect_to;
        }

        return $redirect_to;
    }

    /**
     * Woocommerce Login Redirect functionality helper.
     *
     * @param mixed $redirect Description index.
     * @param mixed $user Description index.
     * @return string Output payload.
     */
    public function woocommerce_login_redirect($redirect, $user): string
    {
        if (in_array('vmp_vendor', (array) $user->roles) || user_can($user, 'vmp_vendor')) {
            return $this->getVendorDashboardUrl();
        }
        return $redirect;
    }

    /**
     * Redirect Vendor From Admin functionality helper.
     *
     * @return void Output payload.
     */
    public function redirect_vendor_from_admin(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();

        if (in_array('administrator', (array) $user->roles) || current_user_can('manage_options')) {
            return;
        }

        if (in_array('vmp_vendor', (array) $user->roles) || user_can($user, 'vmp_vendor')) {
            $current_page = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $allowed_pages = [
                'admin-ajax.php',
                'admin-post.php',
                'logout',
                'profile.php',
                'user-edit.php',
            ];

            foreach ($allowed_pages as $allowed) {
                if (strpos($current_page, $allowed) !== false) {
                    return;
                }
            }

            $dashboard_url = $this->getVendorDashboardUrl();
            wp_redirect($dashboard_url);
            exit;
        }
    }

    /**
     * After Login Redirect functionality helper.
     *
     * @param string $user_login Description index.
     * @param WP_User $user Description index.
     * @return void Output payload.
     */
    public function after_login_redirect(string $user_login, WP_User $user): void
    {
        if (in_array('vmp_vendor', (array) $user->roles) || user_can($user, 'vmp_vendor')) {
            $referer = wp_get_referer();
            if ($referer && strpos($referer, 'wp-login.php') !== false) {
                $dashboard_url = $this->getVendorDashboardUrl();
                wp_redirect($dashboard_url);
                exit;
            }
        }
    }

    /**
     * GetVendorDashboardUrl functionality helper.
     *
     * @return string Output payload.
     */
    private function getVendorDashboardUrl(): string
    {
        $settings = get_option('vmp_settings', []);
        $page_id = !empty($settings['display']['dashboard_page']) ? (int) $settings['display']['dashboard_page'] : 0;

        if ($page_id && get_post($page_id)) {
            return get_permalink($page_id);
        }

        return home_url('/vendor-dashboard/');
    }

    /**
     * Remove Admin Bar Nodes functionality helper.
     *
     * @param \WP_Admin_Bar $wp_admin_bar Description index.
     * @return void Output payload.
     */
    public function remove_admin_bar_nodes(\WP_Admin_Bar $wp_admin_bar): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if (in_array('administrator', (array) $user->roles) || current_user_can('manage_options')) {
            return;
        }

        if (in_array('vmp_vendor', (array) $user->roles) || user_can($user, 'vmp_vendor')) {
            $nodes_to_remove = [
                'wp-logo',
                'site-name',
                'updates',
                'comments',
                'new-content',
                'my-account',
                'user-actions',
                'search',
            ];

            foreach ($nodes_to_remove as $node) {
                $wp_admin_bar->remove_node($node);
            }

            $dashboard_url = $this->getVendorDashboardUrl();
            $wp_admin_bar->add_node([
                'id'    => 'vmp-vendor-dashboard',
                'title' => __('لوحة البائع', 'vmp'),
                'href'  => $dashboard_url,
                'meta'  => ['class' => 'vmp-vendor-dash-link']
            ]);
        }
    }

    /* ═══════════════════════════════════════════════════════════ */
    /* إجراءات AJAX                                               */
    /* ═══════════════════════════════════════════════════════════ */

    /**
     * Ajax Update Profile functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_update_profile(): void
    {
        try {
            $nonce = $_POST['nonce'] ?? $_POST['security'] ?? '';
            if (empty($nonce) || !wp_verify_nonce($nonce, 'vmp_public_nonce')) {
                wp_send_json_error(['message' => __('طلب غير صالح، يرجى تحديث الصفحة.', 'vmp')]);
                return;
            }

            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error(['message' => __('يجب تسجيل الدخول أولاً', 'vmp')]);
                return;
            }

            $repository = $this->vendors();
            $vendor = $repository->findByUserId($user_id);
            if (!$vendor) {
                wp_send_json_error(['message' => __('البائع غير موجود أو لم يتم الموافقة عليه بعد', 'vmp')]);
                return;
            }

            $update_user = ['ID' => $user_id];
            if (!empty($_POST['first_name'])) {
                $update_user['first_name'] = sanitize_text_field($_POST['first_name']);
            }
            if (!empty($_POST['last_name'])) {
                $update_user['last_name'] = sanitize_text_field($_POST['last_name']);
            }
            if (!empty($_POST['user_email']) && is_email($_POST['user_email'])) {
                $update_user['user_email'] = sanitize_email($_POST['user_email']);
            }
            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 6) {
                    wp_send_json_error(['message' => __('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 'vmp')]);
                    return;
                }
                $update_user['user_pass'] = $_POST['password'];
            }

            if (count($update_user) > 1) {
                $result = wp_update_user($update_user);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                    return;
                }
            }

            $vendor_data = [];

            if (!empty($_POST['store_name'])) {
                $vendor_data['store_name'] = sanitize_text_field($_POST['store_name']);
            }
            if (!empty($_POST['description'])) {
                $vendor_data['store_description'] = sanitize_textarea_field($_POST['description']);
            }
            if (!empty($_POST['phone'])) {
                $vendor_data['store_phone'] = sanitize_text_field($_POST['phone']);
            }
            if (isset($_POST['store_address'])) {
                $vendor_data['store_address'] = sanitize_textarea_field($_POST['store_address']);
            }
            if (isset($_POST['social_facebook'])) {
                $vendor_data['social_facebook'] = esc_url_raw($_POST['social_facebook']);
            }
            if (isset($_POST['social_instagram'])) {
                $vendor_data['social_instagram'] = esc_url_raw($_POST['social_instagram']);
            }
            if (isset($_POST['social_twitter'])) {
                $vendor_data['social_twitter'] = esc_url_raw($_POST['social_twitter']);
            }
            if (isset($_POST['social_youtube'])) {
                $vendor_data['social_youtube'] = esc_url_raw($_POST['social_youtube']);
            }
            if (isset($_POST['store_video'])) {
                $vendor_data['store_video'] = esc_url_raw($_POST['store_video']);
            }
            if (isset($_POST['logo_id'])) {
                $vendor_data['store_logo'] = (int) $_POST['logo_id'];
            }
            if (isset($_POST['banner_id'])) {
                $vendor_data['store_banner'] = (int) $_POST['banner_id'];
            }

            if (!empty($vendor_data)) {
                $updated = $repository->update($vendor->id, $vendor_data);
                if ($updated === false) {
                    wp_send_json_error(['message' => __('فشل تحديث بيانات المتجر، يرجى المحاولة مرة أخرى.', 'vmp')]);
                    return;
                }
            }

            try {
                $this->make('event_manager')->trigger('vmp_vendor_profile_updated', $vendor->id, $user_id);
            } catch (\Exception $e) {
                // تجاهل
            }

            wp_send_json_success(['message' => __('تم حفظ التعديلات بنجاح', 'vmp')]);

        } catch (\Exception $e) {
            $this->log_error('خطأ في تحديث الملف الشخصي: ' . $e->getMessage());
            wp_send_json_error(['message' => __('حدث خطأ، يرجى المحاولة مرة أخرى.', 'vmp')]);
        }
    }

    /**
     * ✅ الموافقة على بائع (للمشرف) – تم إصلاحها
     */
    public function ajax_admin_approve(): void
    {
        try {
            // ── 1. التحقق من nonce ──
            $nonce = $_POST['nonce'] ?? $_POST['_wpnonce'] ?? $_POST['security'] ?? '';
            if (empty($nonce) || !wp_verify_nonce($nonce, 'vmp_admin_nonce')) {
                $this->log_error('فشل التحقق من nonce في الموافقة', ['received' => $nonce]);
                wp_send_json_error(['message' => __('طلب غير صالح، يرجى تحديث الصفحة.', 'vmp')]);
                return;
            }

            // ── 2. التحقق من صلاحية المشرف ──
            if (!current_user_can('vmp_manage_vendors') && !current_user_can('manage_options')) {
                $this->log_error('صلاحية غير كافية للموافقة', ['user' => get_current_user_id()]);
                wp_send_json_error(['message' => __('غير مصرح لك بهذا الإجراء.', 'vmp')]);
                return;
            }

            // ── 3. الحصول على معرف البائع ──
            $vendor_id = (int) ($_POST['id'] ?? $_POST['vendor_id'] ?? 0);
            if ($vendor_id <= 0) {
                wp_send_json_error(['message' => __('معرف البائع غير صالح.', 'vmp')]);
                return;
            }

            // ── 4. جلب البائع ──
            $repository = $this->vendors();
            $vendor = $repository->find($vendor_id);
            if (!$vendor) {
                wp_send_json_error(['message' => __('البائع غير موجود.', 'vmp')]);
                return;
            }

            // ── 5. التحقق من أن البائع ليس معتمداً بالفعل ──
            if ($vendor->status === 'approved') {
                wp_send_json_error(['message' => __('هذا البائع معتمد بالفعل.', 'vmp')]);
                return;
            }

            // ── 6. تنفيذ الموافقة ──
            $approved = $repository->approve($vendor_id);

            if ($approved) {
                // ── 7. إضافة دور البائع للمستخدم ──
                $user = new \WP_User($vendor->user_id);
                if (!in_array('vmp_vendor', (array) $user->roles)) {
                    $user->add_role('vmp_vendor');
                }
                update_user_meta($vendor->user_id, 'vmp_vendor_status', 'approved');

                // ── 8. إطلاق حدث الموافقة ──
                try {
                    $this->make('event_manager')->trigger('vmp_vendor_approved', $vendor_id);
                } catch (\Exception $e) {
                    $this->log_error('فشل إطلاق حدث الموافقة: ' . $e->getMessage());
                }

                // ── 9. تسجيل النجاح ──
                $this->log_error('تمت الموافقة على البائع بنجاح', ['vendor_id' => $vendor_id, 'user_id' => $vendor->user_id]);

                wp_send_json_success([
                    'message' => __('تمت الموافقة على البائع بنجاح.', 'vmp'),
                    'vendor_id' => $vendor_id,
                ]);
                return;
            }

            // ── 10. فشل الموافقة ──
            $this->log_error('فشلت الموافقة على البائع', ['vendor_id' => $vendor_id]);
            wp_send_json_error(['message' => __('حدث خطأ أثناء الموافقة، يرجى المحاولة مرة أخرى.', 'vmp')]);

        } catch (\Exception $e) {
            $this->log_error('خطأ غير متوقع في الموافقة: ' . $e->getMessage());
            wp_send_json_error(['message' => __('حدث خطأ غير متوقع.', 'vmp')]);
        }
    }

    /**
     * ✅ رفض بائع (للمشرف)
     */
    public function ajax_admin_reject(): void
    {
        try {
            $nonce = $_POST['nonce'] ?? $_POST['_wpnonce'] ?? $_POST['security'] ?? '';
            if (empty($nonce) || !wp_verify_nonce($nonce, 'vmp_admin_nonce')) {
                $this->log_error('فشل التحقق من nonce في الرفض');
                wp_send_json_error(['message' => __('طلب غير صالح، يرجى تحديث الصفحة.', 'vmp')]);
                return;
            }

            if (!current_user_can('vmp_manage_vendors') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('غير مصرح لك.', 'vmp')]);
                return;
            }

            $vendor_id = (int) ($_POST['id'] ?? $_POST['vendor_id'] ?? 0);
            if ($vendor_id <= 0) {
                wp_send_json_error(['message' => __('معرف البائع غير صالح.', 'vmp')]);
                return;
            }

            $reason = sanitize_textarea_field($_POST['reason'] ?? '');

            $rejected = $this->vendors()->reject($vendor_id, $reason);

            if ($rejected) {
                try {
                    $this->make('event_manager')->trigger('vmp_vendor_rejected', $vendor_id, $reason);
                } catch (\Exception $e) {
                    $this->log_error('فشل إطلاق حدث الرفض: ' . $e->getMessage());
                }

                wp_send_json_success(['message' => __('تم رفض البائع بنجاح.', 'vmp')]);
                return;
            }

            wp_send_json_error(['message' => __('حدث خطأ أثناء الرفض، يرجى المحاولة مرة أخرى.', 'vmp')]);

        } catch (\Exception $e) {
            $this->log_error('خطأ غير متوقع في الرفض: ' . $e->getMessage());
            wp_send_json_error(['message' => __('حدث خطأ غير متوقع.', 'vmp')]);
        }
    }

    /* ═══════════════════════════════════════════════════════════ */
    /* دوال مساعدة للتسجيل والتحقق                                */
    /* ═══════════════════════════════════════════════════════════ */

    /**
     * Log Error functionality helper.
     *
     * @param string $message Description index.
     * @param array $context Description index.
     * @return void Output payload.
     */
    private function log_error(string $message, array $context = []): void
    {
        error_log('VMP Error: ' . $message . ' ' . json_encode($context));

        try {
            $logger = $this->container->get('logger');
            if ($logger && method_exists($logger, 'error')) {
                $logger->error($message, $context);
            }
        } catch (\Exception $e) {
            // تجاهل
        }
    }
}
