<?php
namespace VMP\Modules\Vendor;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Modules\AbstractModule;

/**
 * Class VendorModule
 *
 * Description of administrative platform component VendorModule.
 *
 * @package vendor-marketplace
 */
class VendorModule extends AbstractModule
{
    private VendorHooks $hooks;

    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->hooks = new VendorHooks($container);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        $this->hooks->register();
    }
}
