<?php
namespace VMP\Modules\VendorRegistration\Frontend;

use VMP\Modules\VendorRegistration\Repositories\WpVendorStoreRepository;

class StoreFrontend
{
    public static function init(): void
    {
        add_action('init', [self::class, 'addRewriteRules']);
        add_filter('query_vars', [self::class, 'addQueryVars']);
        add_filter('template_include', [self::class, 'loadStoreTemplate']);
    }

    public static function addRewriteRules(): void
    {
        // vendor-store/{slug}
        add_rewrite_rule('^vendor-store/([^/]+)/?$', 'index.php?vmp_store_slug=$matches[1]', 'top');
    }

    public static function addQueryVars(array $vars): array
    {
        $vars[] = 'vmp_store_slug';
        return $vars;
    }

    public static function loadStoreTemplate(string $template): string
    {
        $slug = get_query_var('vmp_store_slug');
        if (empty($slug)) {
            return $template;
        }

        // try to load plugin template
        $tpl = defined('VMP_PLUGIN_DIR') ? trailingslashit(VMP_PLUGIN_DIR) . 'public/templates/vendor-registration/store-view.php' : __DIR__ . '/../../../public/templates/vendor-registration/store-view.php';
        if (file_exists($tpl)) {
            return $tpl;
        }

        return $template;
    }
}

// bootstrap
add_action('plugins_loaded', function() {
    StoreFrontend::init();
});
