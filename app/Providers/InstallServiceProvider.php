<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Core\Install;

/**
 * Class InstallServiceProvider
 *
 * Description of administrative platform component InstallServiceProvider.
 *
 * @package vendor-marketplace
 */
class InstallServiceProvider extends ServiceProvider
{
    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        register_activation_hook(VMP_PLUGIN_FILE, [Install::class, 'activate']);
        register_deactivation_hook(VMP_PLUGIN_FILE, [Install::class, 'deactivate']);
        register_uninstall_hook(VMP_PLUGIN_FILE, [Install::class, 'uninstall']);
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        // ── [QA 2026-08-06] Self-heal لصلاحيات Shop Manager (منح مرة واحدة عند الفقدان) ──
        add_action('init', static function (): void {
            \VMP\Core\Install::ensure_shop_manager_caps();
        }, 3);

        add_action('init', static function (): void {
            $current_db = get_option('vmp_db_version', '0.0.0');
            if (version_compare($current_db, VMP_VERSION, '<') && !get_transient('vmp_upgrade_lock')) {
                set_transient('vmp_upgrade_lock', 1, 300);
                try {
                    Install::upgrade();
                    update_option('vmp_flush_rewrite', true);
                } finally {
                    delete_transient('vmp_upgrade_lock');
                }
            }
        }, 5);


        add_action('init', static function (): void {
            if (get_option('vmp_flush_rewrite')) {
                delete_option('vmp_flush_rewrite');
                flush_rewrite_rules();
            }
        }, 99);
    }
}
