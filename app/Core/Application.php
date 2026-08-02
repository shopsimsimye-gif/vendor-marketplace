<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

use VMP\Admin\VendorRequestsAdminPage;
use VMP\Repositories\VendorRequestRepository;
use VMP\Services\VendorRegistrationService;
use VMP\Controllers\VendorRegistrationController;
// [QA 2026-08-02] use خاطئ: الكلاس فعلياً في VMP\Controllers\RestVendorRegistrationController
use VMP\Controllers\RestVendorRegistrationController;
use VMP\Modules\VendorRegistration\Shortcodes\VendorRegistrationShortcode;

/**
 * Class Application
 *
 * Description of administrative platform component Application.
 *
 * @package vendor-marketplace
 */
class Application
{
    private string $pluginFile;
    private Container $container;
    private ?Kernel $kernel = null;

    /**
     *   Construct functionality helper.
     *
     * @param string $pluginFile Description index.
     * @return void Output payload.
     */
    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
        $this->container = Container::getInstance(); // ✅ التصحيح
    }

    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        $this->container->instance('app', $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance('config', \VMP\Support\Config::getInstance(VMP_PLUGIN_DIR . 'app/Config'));

        Container::setInstance($this->container);

        $this->container->singleton('logger', function (): Logger {
            return new Logger();
        });

        $this->container->singleton('event_manager', function (): EventManager {
            return new EventManager();
        });

        $this->container->singleton('migration', function (): Migration {
            return new Migration();
        });

        $this->container->singleton(VendorRegistrationService::class, function (): VendorRegistrationService {
            return new VendorRegistrationService();
        });

        $this->container->singleton(VendorRegistrationController::class, function (): VendorRegistrationController {
            return new VendorRegistrationController(
                $this->container->make(VendorRegistrationService::class),
                $this->container->make(Logger::class),
                $this->container->make(ViewRenderer::class)
            );
        });

        $this->container->singleton(RestVendorRegistrationController::class, function (): RestVendorRegistrationController {
            return new RestVendorRegistrationController(
                $this->container->make(VendorRegistrationService::class),
                $this->container->make(Logger::class)
            );
        });

        $this->container->singleton(VendorRequestsAdminPage::class, function (): VendorRequestsAdminPage {
            return new VendorRequestsAdminPage(
                $this->container->make(VendorRequestRepository::class),
                $this->container->make(VendorRegistrationService::class),
                $this->container->make(ViewRenderer::class)
            );
        });

        $this->container->singleton(VendorRegistrationShortcode::class, function (): VendorRegistrationShortcode {
            return new VendorRegistrationShortcode(
                $this->container->make(ViewRenderer::class),
                $this->container->make(VendorRegistrationService::class)
            );
        });

        $this->container->singleton('module_manager', function (): ModuleManager {
            return new ModuleManager($this->container);
        });

        $this->kernel = new Kernel($this->container);
        $this->kernel->register();
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        if ($this->kernel === null) {
            $this->register();
        }

        $this->kernel->boot();
    }

    /**
     * GetContainer functionality helper.
     *
     * @return Container Output payload.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * GetKernel functionality helper.
     *
     * @return ?Kernel Output payload.
     */
    public function getKernel(): ?Kernel
    {
        return $this->kernel;
    }

    /**
     * GetPluginFile functionality helper.
     *
     * @return string Output payload.
     */
    public function getPluginFile(): string
    {
        return $this->pluginFile;
    }
}