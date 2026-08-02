<?php
namespace VMP\Modules\VendorRegistration\Events;

class StoreSetupCompleted
{
    public object $session;

    public function __construct(object $session)
    {
        $this->session = $session;
    }
}
