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
        // [QA 2026-08-05] مسارات تسجيل البائع أحادية الخطوة (register-guest/apply)
        // تُسجَّل الآن في VendorServiceProvider عبر RestVendorRegistrationController.
        // تمت إزالة require_once لـ Rest/routes.php (الوحدة القديمة) لأنها كانت:
        //   1) تُكرر تسجيل register-guest/apply (ازدواج بدون خطأ لكن غير نظيف)
        //   2) تسجّل نقاطاً ميتة: /vendor/register, /vendor/draft, /vendor/store/setup,
        //      /vendor/store, /admin/vendor/* (لا يستخدمها أي JS حي؛ اللوحة الإدارية
        //      تستخدم VendorRequestRepository::approve() مباشرة، لا REST)
        // النسخة الاحتياطية: .qa_backups/dead-routes-remove-20260805/

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