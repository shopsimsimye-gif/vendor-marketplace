<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

/**
 * Class WooCommerceServiceProvider
 *
 * Description of administrative platform component WooCommerceServiceProvider.
 *
 * @package vendor-marketplace
 */
class WooCommerceServiceProvider extends ServiceProvider
{
    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        $this->container->instance('woocommerce.active', class_exists('WooCommerce'));
        $this->container->instance('woocommerce.version', defined('WC_VERSION') ? WC_VERSION : null);
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        // الفحص الآن في الملف الرئيسي — هنا نفترض أن WooCommerce موجود
        
        add_action('before_woocommerce_init', static function (): void {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'custom_order_tables', 
                    VMP_PLUGIN_FILE, 
                    true
                );
            }
        });
    }
}
