<?php
namespace VMP\Modules\VendorRegistration\Events;

class VendorRegistered {
    public function __construct(public object $request) {}
}
