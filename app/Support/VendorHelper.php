<?php
namespace VMP\Support;

defined('ABSPATH') || exit;

use VMP\Repositories\SubscriptionRepository;

/**
 * Class VendorHelper
 *
 * Description of administrative platform component VendorHelper.
 *
 * @package vendor-marketplace
 */
class VendorHelper
{
    /**
     * Get Current Vendor Id functionality helper.
     *
     * @return int Output payload.
     */
    public static function get_current_vendor_id(): int
    {
        global $wpdb;
        $user_id = get_current_user_id();
        if (!$user_id) return 0;

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        return (int) ($id ?? 0);
    }

    /**
     * Get Vendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return ?object Output payload.
     */
    public static function get_vendor(int $vendor_id): ?object
    {
        global $wpdb;
        if (!$vendor_id) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vmp_vendors WHERE id = %d LIMIT 1",
            $vendor_id
        )) ?: null;
    }

    /**
     * Is Vendor functionality helper.
     *
     * @param int $user_id Description index.
     * @return bool Output payload.
     */
    public static function is_vendor(int $user_id = 0): bool
    {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;

        $user = get_userdata($user_id);
        if (!$user || !in_array('vmp_vendor', (array) $user->roles)) return false;

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        return $status === 'approved';
    }

    /**
     * Require Vendor functionality helper.
     *
     * @param bool $require_approved Description index.
     * @return void Output payload.
     */
    public static function require_vendor(bool $require_approved = false): void
    {
        if (!is_user_logged_in()) {
            $current_url = home_url($_SERVER['REQUEST_URI'] ?? '/');
            wp_redirect(wp_login_url($current_url));
            exit;
        }

        $vendor_id = self::get_current_vendor_id();
        if (!$vendor_id) {
            $settings = get_option('vmp_settings', []);
            $register_page_id = !empty($settings['display']['register_page']) ? (int) $settings['display']['register_page'] : 0;
            $register_page = $register_page_id && get_post($register_page_id)
                ? get_permalink($register_page_id)
                : home_url('/vendor-register/');
            wp_redirect($register_page);
            exit;
        }

        if ($require_approved) {
            $vendor = self::get_vendor($vendor_id);
            if ($vendor && $vendor->status !== 'approved') {
                // leave to template to show message
            }
        }
    }

    /**
     * Dashboard Url functionality helper.
     *
     * @param string $page Description index.
     * @return string Output payload.
     */
    public static function dashboard_url(string $page = ''): string
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
     * Get Product Vendor Id functionality helper.
     *
     * @param int $product_id Description index.
     * @return int Output payload.
     */
    public static function get_product_vendor_id(int $product_id): int
    {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT vendor_id FROM {$wpdb->prefix}vmp_vendor_products WHERE product_id = %d LIMIT 1",
            $product_id
        ));
        return (int) ($id ?? 0);
    }

    /**
     * Price functionality helper.
     *
     * @param float $amount Description index.
     * @return string Output payload.
     */
    public static function price(float $amount): string
    {
        return function_exists('wc_price') ? wc_price($amount) : number_format($amount, 2) . ' ر.س';
    }

    /**
     * Can Add Product functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return bool Output payload.
     */
    public static function can_add_product(int $vendor_id = 0): bool
    {
        if (!$vendor_id) $vendor_id = self::get_current_vendor_id();
        if (!$vendor_id) return false;

        $container = \VMP\Core\Container::getInstance();
        $sub_repo = $container->has(SubscriptionRepository::class)
            ? $container->make(SubscriptionRepository::class)
            : new SubscriptionRepository();
        return $sub_repo->canAddProduct($vendor_id);
    }
}
