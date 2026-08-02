<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

/**
 * Class Kernel
 *
 * Description of administrative platform component Kernel.
 *
 * @package vendor-marketplace
 */
class Kernel
{
    private Container $container;

    private array $providers = [
        \VMP\Providers\InstallServiceProvider::class,
        \VMP\Providers\CoreServiceProvider::class,
        \VMP\Providers\EventServiceProvider::class,   // ← نظام الأحداث والمستمعين
        \VMP\Providers\WooCommerceServiceProvider::class,
        \VMP\Providers\AdminServiceProvider::class,
        \VMP\Providers\VendorServiceProvider::class,
        \VMP\Providers\ApiServiceProvider::class,
        \VMP\Providers\CronServiceProvider::class,
    ];

    private array $providerInstances = [];

    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VMP][Kernel] register() started');
        }

        foreach ($this->providers as $providerClass) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP][Kernel] testing provider: ' . $providerClass . ' (class_exists=' . (class_exists($providerClass) ? 'yes' : 'no') . ')');
            }

            if (!class_exists($providerClass)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[VMP][Kernel] provider class missing: ' . $providerClass);
                }
                continue;
            }

            try {
                $provider = new $providerClass($this->container);
                $this->providerInstances[] = $provider;

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[VMP][Kernel] provider instantiated: ' . get_class($provider));
                }

                if (method_exists($provider, 'register')) {
                    $provider->register();
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[VMP][Kernel] provider->register() executed: ' . get_class($provider));
                    }
                }
            } catch (\Throwable $e) {
                error_log('[VMP][Kernel] Exception instantiating/registering provider ' . $providerClass . ': ' . $e->getMessage());
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log($e->getTraceAsString());
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VMP][Kernel] register() completed. Providers instantiated: ' . count($this->providerInstances));
        }
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VMP][Kernel] boot() started');
        }

        // 1. InstallServiceProvider
        foreach ($this->providerInstances as $provider) {
            if ($provider instanceof \VMP\Providers\InstallServiceProvider) {
                if (method_exists($provider, 'boot')) {
                    $provider->boot();
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[VMP][Kernel] InstallServiceProvider->boot() executed');
                    }
                }
                break;
            }
        }

        // 2. WooCommerceServiceProvider
        foreach ($this->providerInstances as $provider) {
            if ($provider instanceof \VMP\Providers\WooCommerceServiceProvider) {
                if (method_exists($provider, 'boot')) {
                    $provider->boot();
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[VMP][Kernel] WooCommerceServiceProvider->boot() executed');
                    }
                }
                break;
            }
        }

        // 3. VendorServiceProvider (يسجل الشورت كودات دائماً)
        foreach ($this->providerInstances as $provider) {
            if ($provider instanceof \VMP\Providers\VendorServiceProvider) {
                if (method_exists($provider, 'boot')) {
                    $provider->boot();
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[VMP][Kernel] VendorServiceProvider->boot() executed');
                    }
                }
                break;
            }
        }

        // 4. التحقق من WooCommerce
        $woocommerceActive = $this->container->has('woocommerce.active')
            && (bool) $this->container->make('woocommerce.active');

        // 5. باقي المزودات
        $skipClasses = [
            \VMP\Providers\InstallServiceProvider::class,
            \VMP\Providers\WooCommerceServiceProvider::class,
            \VMP\Providers\VendorServiceProvider::class,
        ];

        foreach ($this->providerInstances as $provider) {
            if (in_array(get_class($provider), $skipClasses, true)) {
                continue;
            }
            if (method_exists($provider, 'boot')) {
                try {
                    $provider->boot();
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[VMP][Kernel] provider->boot() executed: ' . get_class($provider));
                    }
                } catch (\Throwable $e) {
                    error_log('[VMP][Kernel] Exception during provider->boot ' . get_class($provider) . ': ' . $e->getMessage());
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log($e->getTraceAsString());
                    }
                }
            }
        }

        // 6. تحميل الوحدات (بغض النظر عن WooCommerce)
        $this->registerModules();

        // 7. تحميل ملفات اللغة
        $this->loadTextDomain();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VMP][Kernel] boot() completed');
        }
    }

    /**
     * RegisterModules functionality helper.
     *
     * @return void Output payload.
     */
    public function registerModules(): void
    {
        $manager = $this->container->make('module_manager');
        if (!$manager) {
            return;
        }

        $modules = [
            'vendor',        // ✅ وحدة البائع (تشمل هوكات إعادة التوجيه)
            'product',
            'order',
            'commission',
            'withdrawal',
            'subscription',
            'whatsapp',
            'template',
            'report',
            'notification',
            'settings',
            'ai',
        ];

        foreach ($modules as $module) {
            try {
                $manager->load_module($module);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[VMP][Kernel] module loaded: ' . $module);
                }
            } catch (\Throwable $e) {
                error_log('[VMP][Kernel] Exception loading module ' . $module . ': ' . $e->getMessage());
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log($e->getTraceAsString());
                }
            }
        }
    }

    /**
     * LoadTextDomain functionality helper.
     *
     * @return void Output payload.
     */
    public function loadTextDomain(): void
    {
        if (function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('vmp', false, dirname(VMP_PLUGIN_BASENAME) . '/languages');
        }
    }
}
