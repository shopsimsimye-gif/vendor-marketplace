<?php
namespace VMP\Modules;

defined('ABSPATH') || exit;

use VMP\Core\Container;

/**
 * Class AbstractModule
 *
 * Description of administrative platform component AbstractModule.
 *
 * @package vendor-marketplace
 */
abstract class AbstractModule
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
     * Make functionality helper.
     *
     * @param string $abstract Description index.
     * @return mixed Output payload.
     */
    protected function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }
}
