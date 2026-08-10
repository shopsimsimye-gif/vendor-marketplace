<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

/**
 * Class Kernel
 *
 * @package vendor-marketplace
 */
class Kernel
{
    private Container $container;

    private array $providers = [
        \VMP\Providers\InstallServiceProvider::class,
        \VMP\Providers\CoreServiceProvider::class,
        \VMP\Providers\EventServiceProvider::class,
        \VMP\Providers\WooCommerceServiceProvider::class,
        \VMP\Providers\AdminServiceProvider::class,
        \VMP\Providers\VendorServiceProvider::class,
        \VMP\Providers\ApiServiceProvider::class,
        \VMP\Providers\CronServiceProvider::class,
    ];

    private array $providerInstances = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

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

        // 4. باقي المزودات — ما عدا CoreServiceProvider (سيُستدعى بعد الموديولات)
        $skipClasses = [
            \VMP\Providers\InstallServiceProvider::class,
            \VMP\Providers\WooCommerceServiceProvider::class,
            \VMP\Providers\VendorServiceProvider::class,
            \VMP\Providers\CoreServiceProvider::class, // ← يُستدعى لاحقاً
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

        // 5. تحميل الوحدات (تسجل AJAX/REST routes الخاصة بها)
        $this->registerModules();

        // 6. الآن CoreServiceProvider::boot() — يسجل AJAX hooks بعد اكتمال كل routes
        foreach ($this->providerInstances as $provider) {
            if ($provider instanceof \VMP\Providers\CoreServiceProvider) {
                if (method_exists($provider, 'boot')) {
                    $provider->boot();
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[VMP][Kernel] CoreServiceProvider->boot() executed (after modules)');
                    }
                }
                break;
            }
        }

        // 7. تحميل ملفات اللغة
        $this->loadTextDomain();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VMP][Kernel] boot() completed');
        }
    }

    public function registerModules(): void
    {
        $manager = $this->container->make('module_manager');
        if (!$manager) {
            return;
        }

        $modules = [
            'vendor',
            'order',
            'commission',
            'subscription',
            'whatsapp',
            'template',
            'notification',
            'settings',
            'media',
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

    public function loadTextDomain(): void
    {
        if (function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('vmp', false, dirname(VMP_PLUGIN_BASENAME) . '/languages');
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
