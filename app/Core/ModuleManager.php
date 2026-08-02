<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

/**
 * Class ModuleManager
 *
 * Description of administrative platform component ModuleManager.
 *
 * @package vendor-marketplace
 */
class ModuleManager
{
    protected Container $container;

    /** @var array<string, object> */
    protected array $loaded = [];

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
     * Load Module functionality helper.
     *
     * @param string $name Description index.
     * @return ?object Output payload.
     */
    public function load_module(string $name): ?object
    {
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }

        $class = $this->resolveModuleClass($name);
        if ($class === null) {
            return null;
        }

        $this->bootModuleProvider($name);

        $instance = $this->container->has($class)
            ? $this->container->make($class)
            : new $class($this->container);

        if (method_exists($instance, 'init')) {
            $instance->init();
        }

        $this->loaded[$name] = $instance;

        return $instance;
    }

    /**
     * Get Module functionality helper.
     *
     * @param string $name Description index.
     * @return ?object Output payload.
     */
    public function get_module(string $name): ?object
    {
        return $this->load_module($name);
    }

    /**
     * ResolveModuleClass functionality helper.
     *
     * @param string $name Description index.
     * @return ?string Output payload.
     */
    private function resolveModuleClass(string $name): ?string
    {
        $uc = ucfirst(strtolower($name));
        $candidates = [
            "VMP\\Modules\\{$uc}\\{$uc}Module",
            "VMP\\Modules\\{$uc}",
        ];

        if (strtolower($name) === 'ai') {
            array_unshift($candidates, 'VMP\\Modules\\AI\\AIModule');
        }

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        $legacy_file = VMP_PLUGIN_DIR . 'modules/class-' . strtolower($name) . '.php';
        if (is_file($legacy_file)) {
            require_once $legacy_file;
            $legacy_class = "VMP\\Modules\\{$uc}";
            if (class_exists($legacy_class)) {
                return $legacy_class;
            }
        }

        return null;
    }

    /**
     * BootModuleProvider functionality helper.
     *
     * @param string $name Description index.
     * @return void Output payload.
     */
    private function bootModuleProvider(string $name): void
    {
        $uc = ucfirst(strtolower($name));
        $providerClass = "VMP\\Modules\\{$uc}\\{$uc}ServiceProvider";

        if (strtolower($name) === 'ai') {
            $providerClass = 'VMP\\Modules\\AI\\AIServiceProvider';
        }

        if (!class_exists($providerClass)) {
            return;
        }

        $provider = new $providerClass($this->container);

        if (method_exists($provider, 'register')) {
            $provider->register();
        }

        if (method_exists($provider, 'boot')) {
            $provider->boot();
        }
    }
}
