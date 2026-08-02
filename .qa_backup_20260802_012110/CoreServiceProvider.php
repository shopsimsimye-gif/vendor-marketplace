<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\CommissionRepositoryInterface;
use VMP\Contracts\OrderRepositoryInterface;
use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\SubscriptionPlanRepositoryInterface;
use VMP\Contracts\SubscriptionRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\VendorRequestRepositoryInterface;
use VMP\Contracts\WithdrawalRepositoryInterface;
use VMP\Repositories\CommissionRepository;
use VMP\Repositories\OrderRepository;
use VMP\Repositories\ProductRepository;
use VMP\Repositories\SubscriptionPlanRepository;
use VMP\Repositories\SubscriptionRepository;
use VMP\Repositories\VendorRepository;
use VMP\Repositories\VendorRequestRepository;
use VMP\Repositories\WithdrawalRepository;
use VMP\Services\VendorService;
use VMP\Services\NotificationService;
use VMP\Services\ProductService;
use VMP\Services\OrderService;
use VMP\Services\CommissionService;
use VMP\Services\SubscriptionService;
use VMP\Services\WithdrawalService;
use VMP\Services\WhatsappService;
use VMP\Services\VendorRegistrationService;
use VMP\Core\EventManager;
use VMP\Core\Logger;
use VMP\Core\ViewRenderer;
use VMP\Core\Queue\QueueManager;
use VMP\Infrastructure\Dispatcher\ExceptionHandler;
use VMP\Infrastructure\Dispatcher\RouteRegistry;
use VMP\Infrastructure\Dispatcher\ControllerMethodResolver;
use VMP\Infrastructure\Dispatcher\ActionDispatcher;
use VMP\Controllers\VendorController;
use VMP\Controllers\ProductController;
use VMP\Controllers\OrderController;
use VMP\Controllers\CommissionController;
use VMP\Controllers\SubscriptionController;
use VMP\Controllers\WithdrawalController;
use VMP\Controllers\WhatsappController;
use VMP\Controllers\VendorRegistrationController;
use VMP\Controllers\AiSettingsController;

/**
 * Class CoreServiceProvider
 *
 * يسجّل البنية الأساسية: Config, Helpers, Utilities, Repositories, Services,
 * Dispatcher Infrastructure, Controllers و Routes.
 *
 * @package vendor-marketplace
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerHelpers();
        $this->registerCoreUtilities();
        $this->registerRepositories();
        $this->registerServices();
        $this->registerControllers();
        $this->registerRoutes();
    }

    /**
     * تسجيل Config.
     */
    protected function registerConfig(): void
    {
        $this->container->singleton('config', function (): \VMP\Support\Config {
            return \VMP\Support\Config::getInstance(VMP_PLUGIN_DIR . 'app/Config');
        });
    }

    /**
     * تحميل دوال المساعدة العامة (vmp_get_product_vendor_id, vmp_get_vendor, ...).
     */
    protected function registerHelpers(): void
    {
        $helpersFile = VMP_PLUGIN_DIR . 'app/Support/helpers.php';
        if (is_file($helpersFile)) {
            require_once $helpersFile;
        }
    }

    /**
     * تسجيل الأدوات الأساسية (EventManager, Logger, QueueManager, Dispatcher...).
     */
    protected function registerCoreUtilities(): void
    {
        $this->container->singleton(EventManager::class, static fn(): EventManager => new EventManager());
        $this->container->singleton(Logger::class, static fn(): Logger => new Logger());
        $this->container->singleton(ViewRenderer::class, static fn(): ViewRenderer => new ViewRenderer());
        $this->container->singleton(QueueManager::class, function (): QueueManager {
            return new QueueManager(
                $this->container,
                $this->container->make(Logger::class),
                $GLOBALS['wpdb']
            );
        });

        $this->container->singleton(ExceptionHandler::class, function () {
            return new ExceptionHandler($this->container->make(Logger::class));
        });

        $this->container->singleton(RouteRegistry::class, function () {
            return new RouteRegistry();
        });

        $this->container->singleton(ControllerMethodResolver::class, function () {
            return new ControllerMethodResolver();
        });

        $this->container->singleton(ActionDispatcher::class, function () {
            return new ActionDispatcher(
                $this->container,
                $this->container->make(RouteRegistry::class),
                $this->container->make(ExceptionHandler::class),
                $this->container->make(ControllerMethodResolver::class)
            );
        });
    }

    /**
     * تسجيل Repositories (Concrete Classes + Interface Bindings).
     */
    protected function registerRepositories(): void
    {
        $this->container->singleton(
            SubscriptionPlanRepository::class,
            static fn(): SubscriptionPlanRepository => new SubscriptionPlanRepository()
        );

        $this->container->singleton(
            SubscriptionRepository::class,
            fn(): SubscriptionRepository => new SubscriptionRepository(
                $this->container->make(SubscriptionPlanRepositoryInterface::class)
            )
        );

        $this->container->singleton(
            VendorRepository::class,
            static fn(): VendorRepository => new VendorRepository()
        );

        $this->container->singleton(
            ProductRepository::class,
            static fn(): ProductRepository => new ProductRepository()
        );

        $this->container->singleton(
            OrderRepository::class,
            static fn(): OrderRepository => new OrderRepository()
        );

        $this->container->singleton(
            CommissionRepository::class,
            static fn(): CommissionRepository => new CommissionRepository()
        );

        $this->container->singleton(
            WithdrawalRepository::class,
            static fn(): WithdrawalRepository => new WithdrawalRepository()
        );

        $this->container->singleton(
            VendorRequestRepository::class,
            static fn(): VendorRequestRepository => new VendorRequestRepository()
        );

        // ─── Interface Bindings (Interfaces → Concrete Classes with Decorators) ───
        $this->container->singleton(VendorRepositoryInterface::class, function () {
            return new \VMP\Repositories\Cached\CachedVendorRepository(
                $this->container->make(VendorRepository::class)
            );
        });

        $this->container->singleton(ProductRepositoryInterface::class, function () {
            return new \VMP\Repositories\Cached\CachedProductRepository(
                $this->container->make(ProductRepository::class)
            );
        });

        $this->container->singleton(VendorRequestRepositoryInterface::class, function () {
            return $this->container->make(VendorRequestRepository::class);
        });

        $interfaceMap = [
            OrderRepositoryInterface::class           => OrderRepository::class,
            CommissionRepositoryInterface::class      => CommissionRepository::class,
            WithdrawalRepositoryInterface::class      => WithdrawalRepository::class,
            SubscriptionRepositoryInterface::class    => SubscriptionRepository::class,
            SubscriptionPlanRepositoryInterface::class=> SubscriptionPlanRepository::class,
        ];

        foreach ($interfaceMap as $interface => $concrete) {
            $this->container->singleton(
                $interface,
                fn(): object => $this->container->make($concrete)
            );
        }
    }

    /**
     * تسجيل Services.
     */
    protected function registerServices(): void
    {
        $this->container->singleton(
            VendorService::class,
            fn(): VendorService => new VendorService(
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(VendorRequestRepositoryInterface::class),
                $this->container->make(ProductRepositoryInterface::class),
                $this->container->make(OrderRepositoryInterface::class),
                $this->container->make(EventManager::class),
                $this->container->make(Logger::class)
            )
        );

        $this->container->singleton(
            NotificationService::class,
            fn(): NotificationService => new NotificationService(
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(VendorRequestRepositoryInterface::class),
                $this->container->make(QueueManager::class)
            )
        );

        $this->container->singleton(
            ProductService::class,
            fn(): ProductService => new ProductService(
                $this->container->make(ProductRepositoryInterface::class),
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(EventManager::class),
                $this->container->make(Logger::class),
                $GLOBALS['wpdb']
            )
        );

        $this->container->singleton(
            OrderService::class,
            fn(): OrderService => new OrderService(
                $this->container->make(OrderRepositoryInterface::class),
                $this->container->make(CommissionRepositoryInterface::class),
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(ProductRepositoryInterface::class),
                $this->container->make(CommissionService::class),
                $this->container->make(EventManager::class),
                $this->container->make(Logger::class)
            )
        );

        // ✅ الخدمات التي كانت مفقودة من الحاوية
        $this->container->singleton(
            CommissionService::class,
            fn(): CommissionService => new CommissionService(
                $this->container->make(CommissionRepositoryInterface::class),
                $this->container->make(SubscriptionPlanRepositoryInterface::class),
                $this->container->make(SubscriptionRepositoryInterface::class),
                $this->container->make(EventManager::class),
                $this->container->make(Logger::class)
            )
        );

        $this->container->singleton(
            SubscriptionService::class,
            fn(): SubscriptionService => new SubscriptionService(
                $this->container->make(SubscriptionRepositoryInterface::class),
                $this->container->make(SubscriptionPlanRepositoryInterface::class),
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(ProductRepositoryInterface::class),
                $this->container->make(EventManager::class),
                $this->container->make(Logger::class)
            )
        );

        $this->container->singleton(
            WithdrawalService::class,
            fn(): WithdrawalService => new WithdrawalService(
                $this->container->make(WithdrawalRepositoryInterface::class),
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(EventManager::class),
                $this->container->make(Logger::class)
            )
        );

        $this->container->singleton(
            WhatsappService::class,
            fn(): WhatsappService => new WhatsappService(
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(SubscriptionService::class),
                $GLOBALS['wpdb']
            )
        );

        $this->container->singleton(
            VendorRegistrationService::class,
            fn(): VendorRegistrationService => new VendorRegistrationService(
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(VendorRequestRepositoryInterface::class)
            )
        );
    }

    /**
     * تسجيل Controllers.
     */
    protected function registerControllers(): void
    {
        $this->container->singleton(VendorController::class, function () {
            return new VendorController($this->container->make(VendorService::class));
        });

        $this->container->singleton(ProductController::class, function () {
            return new ProductController(
                $this->container->make(ProductService::class),
                $this->container->make(Logger::class)
            );
        });

        $this->container->singleton(OrderController::class, function () {
            return new OrderController($this->container->make(OrderService::class));
        });

        $this->container->singleton(CommissionController::class, function () {
            return new CommissionController($this->container->make(CommissionService::class));
        });

        $this->container->singleton(SubscriptionController::class, function () {
            return new SubscriptionController(
                $this->container->make(SubscriptionService::class),
                $this->container->make(SubscriptionPlanRepositoryInterface::class)
            );
        });

        $this->container->singleton(WithdrawalController::class, function () {
            return new WithdrawalController(
                $this->container->make(WithdrawalService::class),
                $this->container->make(WithdrawalRepositoryInterface::class)
            );
        });

        $this->container->singleton(VendorRegistrationController::class, function () {
            return new VendorRegistrationController(
                $this->container->make(VendorRegistrationService::class),
                $this->container->make(Logger::class),
                $this->container->make(ViewRenderer::class)
            );
        });

        $this->container->singleton(WhatsappController::class, function () {
            return new WhatsappController($this->container->make(WhatsappService::class));
        });

        $this->container->singleton(AiSettingsController::class, function () {
            return new AiSettingsController($this->container->make(Logger::class));
        });
    }

    /**
     * RegisterRoutes functionality helper.
     *
     * @return void Output payload.
     */
    protected function registerRoutes(): void
    {
        /** @var RouteRegistry $registry */
        $registry = $this->container->make(RouteRegistry::class);

        // ─── AI Settings Routes (نُقلت من add_action المباشر إلى Controller) ───
        $registry->ajax('vmp_admin_save_ai_settings', AiSettingsController::class, 'save',           false, 'vmp_admin_nonce');
        $registry->ajax('vmp_ai_test_connection',      AiSettingsController::class, 'testConnection', false, 'vmp_admin_nonce');

        // Vendor Routes
        $registry->ajax('vmp_vendor_register',        VendorController::class, 'registerVendor',  true,  'vmp_vendor_registration_nonce', 'register_nonce');
        $registry->ajax('vmp_vendor_update_profile',  VendorController::class, 'updateProfile',   false, 'vmp_public_nonce');
        $registry->ajax('vmp_admin_approve_vendor',   VendorController::class, 'adminApprove',    false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_reject_vendor',    VendorController::class, 'adminReject',     false, 'vmp_admin_nonce');

        // ─── مسارات Multi-step Registration ─────────────────────────────
        $registry->ajax('vmp_vendor_registration_step1',    VendorRegistrationController::class, 'step1VerifyUser',  true, 'vmp_vendor_registration_nonce');
        $registry->ajax('vmp_vendor_registration_step2',    VendorRegistrationController::class, 'step2StoreData',   true, 'vmp_vendor_registration_nonce');
        $registry->ajax('vmp_vendor_registration_submit',   VendorRegistrationController::class, 'submitRequest',    true, 'vmp_vendor_registration_nonce');
        $registry->ajax('vmp_check_store_slug',             VendorRegistrationController::class, 'checkSlugAvailability',        true);
        $registry->ajax('vmp_check_email',                  VendorRegistrationController::class, 'checkEmailAvailability',       true);

        // Product Routes
        $registry->ajax('vmp_add_product',            ProductController::class, 'addProduct',     false, 'vmp_public_nonce');
        $registry->ajax('vmp_update_product',         ProductController::class, 'updateProduct',  false, 'vmp_public_nonce');
        $registry->ajax('vmp_delete_product',         ProductController::class, 'deleteProduct',  false, 'vmp_public_nonce');
        $registry->ajax('vmp_admin_approve_product',  ProductController::class, 'adminApprove',   false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_reject_product',   ProductController::class, 'adminReject',    false, 'vmp_admin_nonce');

        // Order Routes
        $registry->ajax('vmp_get_vendor_orders',      OrderController::class, 'adminGetVendorOrders', false, 'vmp_admin_nonce');
        $registry->ajax('vmp_get_order_details',      OrderController::class, 'getOrderDetails',      false, 'vmp_public_nonce');
        $registry->ajax('vmp_vendor_orders',          OrderController::class, 'getVendorOrders',      false, 'vmp_public_nonce');

        // Commission Routes
        $registry->ajax('vmp_get_commissions',        CommissionController::class, 'adminGetCommissions', false, 'vmp_admin_nonce');
        $registry->ajax('vmp_pay_commission',         CommissionController::class, 'payCommission',       false, 'vmp_admin_nonce');
        $registry->ajax('vmp_bulk_pay_commissions',   CommissionController::class, 'bulkPayCommissions',  false, 'vmp_admin_nonce');
        $registry->ajax('vmp_get_commission_stats',   CommissionController::class, 'adminGetStats',       false, 'vmp_admin_nonce');
        $registry->ajax('vmp_vendor_get_commissions', CommissionController::class, 'vendorGetCommissions',false, 'vmp_public_nonce');
        $registry->ajax('vmp_vendor_commission_chart',CommissionController::class, 'vendorGetChart',      false, 'vmp_public_nonce');

        // Subscription Routes
        $registry->ajax('vmp_get_plans',                    SubscriptionController::class, 'getPlans',                   true);  // عام — لا يحتاج nonce
        $registry->ajax('vmp_subscribe',                    SubscriptionController::class, 'subscribe',                  false, 'vmp_public_nonce');
        $registry->ajax('vmp_upgrade_plan',                 SubscriptionController::class, 'subscribe',                  false, 'vmp_public_nonce');
        $registry->ajax('vmp_cancel_subscription',          SubscriptionController::class, 'cancelSubscription',         false, 'vmp_public_nonce');
        $registry->ajax('vmp_request_plan_change',          SubscriptionController::class, 'requestPlanChange',          false, 'vmp_public_nonce');
        $registry->ajax('vmp_cancel_plan_change',           SubscriptionController::class, 'cancelPlanChange',           false, 'vmp_public_nonce');
        $registry->ajax('vmp_admin_create_plan',            SubscriptionController::class, 'adminCreatePlan',            false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_update_plan',            SubscriptionController::class, 'adminUpdatePlan',            false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_delete_plan',            SubscriptionController::class, 'adminDeletePlan',            false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_get_vendor_subscription',SubscriptionController::class, 'adminGetVendorSubscription', false, 'vmp_admin_nonce');
        $registry->ajax('vmp_get_pending_plan_changes',     SubscriptionController::class, 'adminGetPendingPlanChanges', false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_approve_plan_change',    SubscriptionController::class, 'adminApprovePlanChange',     false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_reject_plan_change',     SubscriptionController::class, 'adminRejectPlanChange',      false, 'vmp_admin_nonce');

        // Withdrawal Routes
        $registry->ajax('vmp_request_withdrawal',       WithdrawalController::class, 'requestWithdrawal',    false, 'vmp_public_nonce');
        $registry->ajax('vmp_admin_get_withdrawals',    WithdrawalController::class, 'adminGetWithdrawals',  false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_process_withdrawal', WithdrawalController::class, 'adminProcessWithdrawal',false,'vmp_admin_nonce');

        // WhatsApp Routes
        $registry->ajax('vmp_track_whatsapp_click',          WhatsappController::class, 'trackClick',           true);  // عام — لا يحتاج nonce
        $registry->ajax('vmp_save_whatsapp_settings',        WhatsappController::class, 'saveSettings',         false, 'vmp_public_nonce');
        $registry->ajax('vmp_get_whatsapp_stats',            WhatsappController::class, 'getStats',             false, 'vmp_public_nonce');
        $registry->ajax('vmp_admin_whatsapp_settings',       WhatsappController::class, 'adminSettings',        false, 'vmp_admin_nonce');
        $registry->ajax('vmp_admin_get_whatsapp_stats',      WhatsappController::class, 'adminGetStats',        false, 'vmp_admin_nonce');
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        /** @var ActionDispatcher $dispatcher */
        $dispatcher = $this->container->make(ActionDispatcher::class);
        $dispatcher->registerAjaxHooks();
    }
}
