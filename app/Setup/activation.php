<?php
/**
 * Activation hook: run migrations and add vendor role & capabilities.
 */
if (!defined('ABSPATH')) {
    exit;
}

$plugin_root = dirname(__DIR__, 2);

register_activation_hook($plugin_root . '/vendor-marketplace.php', function () use ($plugin_root): void {
    if (!function_exists('get_role') || !function_exists('add_role') || !function_exists('flush_rewrite_rules')) {
        return;
    }

    $migrations_dir = $plugin_root . '/Database/Migrations';
    if (is_dir($migrations_dir)) {
        foreach (glob($migrations_dir . '/*.php') as $file) {
            if (is_file($file)) {
                include_once $file;
            }
        }
    }

    if (!get_role('vmp_vendor')) {
        add_role('vmp_vendor', 'Vendor', [
            'read' => true,
        ]);
    }

    $cap = 'manage_vmp_requests';
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap($cap)) {
        $admin->add_cap($cap);
    }

    $shop_manager = get_role('shop_manager');
    if ($shop_manager && !$shop_manager->has_cap($cap)) {
        $shop_manager->add_cap($cap);
    }

    flush_rewrite_rules();
});

register_deactivation_hook($plugin_root . '/vendor-marketplace.php', function () use ($plugin_root): void {
    if (!function_exists('get_role') || !function_exists('flush_rewrite_rules')) {
        return;
    }

    $cap = 'manage_vmp_requests';
    $admin = get_role('administrator');
    if ($admin && $admin->has_cap($cap)) {
        $admin->remove_cap($cap);
    }

    $shop_manager = get_role('shop_manager');
    if ($shop_manager && $shop_manager->has_cap($cap)) {
        $shop_manager->remove_cap($cap);
    }

    flush_rewrite_rules();
});
