<?php
namespace VMP\Modules\VendorRegistration\Frontend;

class Assets
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueueScripts']);
    }

    public static function enqueueScripts(): void
    {
        if (!defined('VMP_PLUGIN_URL')) return;
        $textdomain = 'vendor-marketplace';

        // Register main bundle (index) which imports modules
        $handle = 'vmp-store-setup';
        $src = trailingslashit(VMP_PLUGIN_URL) . 'assets/js/vendor/store-setup/index.js';
        wp_register_script($handle, $src, ['wp-i18n'], filemtime(trailingslashit(VMP_PLUGIN_DIR) . 'assets/js/vendor/store-setup/index.js'), true);

        // Ensure wp-i18n is available
        wp_enqueue_script('wp-i18n');

        // Provide translations to the script
        wp_set_script_translations($handle, $textdomain, trailingslashit(VMP_PLUGIN_DIR) . 'languages');

        // Localize dynamic settings
        $settings = [
            'restBase' => esc_url_raw(rest_url('vmp/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => esc_url(trailingslashit(VMP_PLUGIN_URL)),
            'textDomain' => $textdomain,
            'debug' => defined('WP_DEBUG') && WP_DEBUG ? true : false,
            'sessionTtlDays' => (int) get_option('vmp_store_setup_session_ttl_days', 30),
        ];
        wp_add_inline_script($handle, 'window.VMP_StoreSetup = ' . wp_json_encode($settings) . ';', 'before');

        wp_enqueue_script($handle);

        // Enqueue CSS
        wp_enqueue_style('vmp-store-setup-css', trailingslashit(VMP_PLUGIN_URL) . 'assets/css/vendor/store-setup-wizard.css', [], filemtime(trailingslashit(VMP_PLUGIN_DIR) . 'assets/css/vendor/store-setup-wizard.css'));
    }
}

add_action('plugins_loaded', function(){ Assets::init(); });
