<?php
namespace VMP\Modules\VendorRegistration\Events;

class VendorActivated
{
    public object $request;
    public int $adminId;
    public string $timestamp;

    public function __construct(object $request, int $adminId)
    {
        $this->request = $request;
        $this->adminId = $adminId;
        $this->timestamp = current_time('mysql', 1);
    }
}
