<?php
namespace VMP\Modules\VendorRegistration\Events;

class VendorRejected
{
    public object $request;
    public int $adminId;
    public string $reason;
    public string $timestamp;

    public function __construct(object $request, int $adminId, string $reason)
    {
        $this->request = $request;
        $this->adminId = $adminId;
        $this->reason = $reason;
        $this->timestamp = current_time('mysql', 1);
    }
}
