<?php
namespace VMP\Modules\VendorRegistration\Frontend;

class SetupFrontend
{
    public static function init(): void
    {
        add_action('init', [self::class, 'addRewriteRules']);
        add_filter('query_vars', [self::class, 'addQueryVars']);
        add_filter('template_include', [self::class, 'loadSetupTemplate']);
    }

    public static function addRewriteRules(): void
    {
        add_rewrite_rule('^vendor/store/setup/?$', 'index.php?vmp_store_setup=1', 'top');
        add_rewrite_rule('^vendor/store/status/?$', 'index.php?vmp_store_status=1', 'top');
    }

    public static function addQueryVars(array $vars): array
    {
        $vars[] = 'vmp_store_setup';
        $vars[] = 'vmp_store_status';
        return $vars;
    }

    public static function loadSetupTemplate(string $template): string
    {
        $setupFlag = get_query_var('vmp_store_setup');
        $statusFlag = get_query_var('vmp_store_status');
        if (empty($setupFlag) && empty($statusFlag)) return $template;

        if (!empty($setupFlag)) {
            $tpl = defined('VMP_PLUGIN_DIR') ? trailingslashit(VMP_PLUGIN_DIR) . 'public/templates/vendor-registration/store-setup-wizard.php' : __DIR__ . '/../../../public/templates/vendor-registration/store-setup-wizard.php';
            if (file_exists($tpl)) return $tpl;
        }

        if (!empty($statusFlag)) {
            $tpl = defined('VMP_PLUGIN_DIR') ? trailingslashit(VMP_PLUGIN_DIR) . 'public/templates/vendor-registration/store-status.php' : __DIR__ . '/../../../public/templates/vendor-registration/store-status.php';
            if (file_exists($tpl)) return $tpl;
        }

        return $template;
    }
}

add_action('plugins_loaded', function() {
    SetupFrontend::init();
});
