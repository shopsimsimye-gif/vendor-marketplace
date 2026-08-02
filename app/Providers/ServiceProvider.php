<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Core\Container;

/**
 * Class ServiceProvider
 *
 * Description of administrative platform component ServiceProvider.
 *
 * @package vendor-marketplace
 */
abstract class ServiceProvider
{
    protected Container $container;

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
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
    }
}