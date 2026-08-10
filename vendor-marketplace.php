<?php
/**
 * Plugin Name: Vendor Marketplace
 * Description: Multi-vendor marketplace plugin with registration, onboarding, and vendor management.
 * Version: 1.0.0
 * Author: Your Team
 */

defined('ABSPATH') || exit;

// Autoload
require_once __DIR__ . '/vendor/autoload.php';

use VMP\Core\Application;
use VMP\Modules\VendorRegistration\Config\Capabilities;
use VMP\Modules\VendorRegistration\Services\CapabilityManager;

// Const
if (!defined('VMP_PLUGIN_URL')) {
    define('VMP_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('VMP_PLUGIN_PATH')) {
    define('VMP_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('VMP_PLUGIN_DIR')) {
    define('VMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('VMP_PLUGIN_FILE')) {
    define('VMP_PLUGIN_FILE', __FILE__);
}
if (!defined('VMP_PLUGIN_BASENAME')) {
    define('VMP_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if (!defined('VMP_VERSION')) {
    define('VMP_VERSION', '1.0.1');
}

// ────────────────────────────────────────────────
// Fix: WoodMart theme missing CSS paths (WP 7.0+)
// Prevents PHP notices from polluting debug.log.
// ────────────────────────────────────────────────
add_action('wp_default_styles', function (WP_Styles $wp_styles) {
    $woodmart_handles = [
        'wd-style-base',
        'wd-header-base',
        'wd-page-search-results',
        'wd-int-rank-math',
        'wd-elementor-base',
        'wd-woocommerce-base',
        'wd-woo-shop-page-title',
        'wd-woo-mod-shop-loop-head',
    ];

    foreach ($woodmart_handles as $handle) {
        if (!isset($wp_styles->registered[$handle])) {
            continue;
        }

        $style = $wp_styles->registered[$handle];

        // Already has a valid absolute path — nothing to do
        if (!empty($style->extra['path']) && file_exists($style->extra['path'])) {
            continue;
        }

        // Rebuild absolute path from the registered src
        if (empty($style->src)) {
            continue;
        }

        $relative_path = ltrim(parse_url($style->src, PHP_URL_PATH) ?? '', '/');
        $absolute_path = ABSPATH . $relative_path;

        if (file_exists($absolute_path)) {
            $wp_styles->registered[$handle]->extra['path'] = $absolute_path;
        }
    }
});

add_action('parse_request', function ($wp) {
    if (is_admin()) {
        return;
    }

    $slug = \VMP\Support\VirtualStore::resolveVendorSlugFromRequest($_SERVER, $_GET);
    if ($slug === '') {
        return;
    }

    $wp->query_vars['vendor_store'] = $slug;
    $wp->query_vars['name'] = '';
    $wp->query_vars['pagename'] = '';
    $wp->query_vars['page_id'] = '';
    $wp->query_vars['category_name'] = '';
    $wp->query_vars['product_cat'] = '';
    $wp->query_vars['post_type'] = '';

    $wp->is_404 = false;
    $wp->is_page = true;
    $wp->is_singular = true;
    $wp->is_home = false;
    $wp->is_archive = false;
    $wp->is_category = false;
    $wp->is_product_category = false;
});

add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }

    if (function_exists('VMP\setup_virtual_store_page')) {
        \VMP\setup_virtual_store_page();
    }
}, 1);

// Boot
add_action('plugins_loaded', function () {
    // Load textdomain
    load_plugin_textdomain('vmp', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Ensure capabilities are provisioned (run once on version mismatch)
    if (function_exists('get_option') && function_exists('update_option')) {
        $current = get_option('vmp_capabilities_version', '');
        if ($current !== Capabilities::CAPABILITIES_VERSION) {
            CapabilityManager::register();
            update_option('vmp_capabilities_version', Capabilities::CAPABILITIES_VERSION);
        }
    }

    // Boot plugin via Application (new architecture)
    $plugin = new Application(__FILE__);
    $plugin->boot();
});

// Activation hook
register_activation_hook(__FILE__, function () {
    // Ensure capabilities exist for new installs
    \VMP\Modules\VendorRegistration\Services\CapabilityManager::register();

    update_option(
        'vmp_capabilities_version',
        \VMP\Modules\VendorRegistration\Config\Capabilities::CAPABILITIES_VERSION
    );

    $migration = __DIR__ . '/app/Database/Migrations/CreateVendorTables.php';
    if (file_exists($migration)) {
        require_once $migration;
        \VMP\Database\Migrations\CreateVendorTables::up();
    }

    // Flush rewrite rules
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
