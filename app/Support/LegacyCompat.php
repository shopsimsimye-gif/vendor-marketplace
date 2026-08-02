<?php
namespace VMP\Support;

defined('ABSPATH') || exit;

/**
 * Transitional compatibility — global helpers and legacy class aliases only.
 */
class LegacyCompat
{
    /** @var array<string, class-string> */
    private static array $classAliases = [
        'VMP_Module_Manager' => \VMP\Core\ModuleManager::class,
        'VMP_Container'      => \VMP\Core\Container::class,
        'VMP_Install'        => \VMP\Core\Install::class,
        'VMP_Logger'         => \VMP\Core\Logger::class,
        'VMP_Event_Manager'  => \VMP\Core\EventManager::class,
        'VMP_Migration'      => \VMP\Core\Migration::class,
    ];

    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public static function register(): void
    {
        $helpers = VMP_PLUGIN_DIR . 'includes/helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }

        self::registerClassAliases();
    }

    /**
     * RegisterClassAliases functionality helper.
     *
     * @return void Output payload.
     */
    private static function registerClassAliases(): void
    {
        foreach (self::$classAliases as $alias => $class) {
            if (!class_exists($alias, false) && class_exists($class)) {
                class_alias($class, $alias);
            }
        }
    }

    /**
     * Lazy-load a legacy file only when explicitly requested (e.g. third-party code).
     */
    public static function requireLegacyFile(string $relativePath): void
    {
        $path = VMP_PLUGIN_DIR . ltrim($relativePath, '/');
        if (is_file($path)) {
            require_once $path;
        }
    }
}
