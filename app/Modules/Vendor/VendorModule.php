<?php
namespace VMP\Modules\Vendor;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Modules\AbstractModule;

/**
 * Class VendorModule
 *
 * @package vendor-marketplace
 */
class VendorModule extends AbstractModule
{
    /**
     * Construct functionality helper.
     *
     * @param Container $container
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        // VendorHooks removed (legacy) — AJAX routes registered in CoreServiceProvider via VendorController
    }

    /**
     * Init functionality helper.
     *
     * @return void
     */
    public function init(): void
    {
        // No legacy hooks to register
    }
}
