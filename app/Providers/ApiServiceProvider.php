<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Http\Controllers\Api\VendorApiController;
use VMP\Http\Controllers\Api\ProductApiController;

/**
 * ApiServiceProvider — يُسجل مسارات REST API الخاصة بالإضافة
 */
class ApiServiceProvider extends ServiceProvider
{
    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        // يمكن تسجيل الـ Controllers في الحاوية إذا لزم الأمر
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        // تحميل مسارات تسجيل البائع
        $registration_routes = VMP_PLUGIN_DIR . 'app/Modules/VendorRegistration/Rest/routes.php';
        if (file_exists($registration_routes)) {
            require_once $registration_routes;
        }

        add_action('rest_api_init', function () {
            // تسجيل مسارات البائعين
            $vendorApi = $this->container->make(VendorApiController::class);
            $vendorApi->registerRoutes();

            // تسجيل مسارات المنتجات
            $productApi = $this->container->make(ProductApiController::class);
            $productApi->registerRoutes();
        });
    }
}