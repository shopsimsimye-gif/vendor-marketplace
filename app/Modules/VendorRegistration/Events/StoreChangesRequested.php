<?php
namespace VMP\Modules\VendorRegistration\Events;

class StoreChangesRequested
{
    public object $request;
    public int $adminId;
    public string $message;
    public string $timestamp;

    public function __construct(object $request, int $adminId, string $message)
    {
        $this->request = $request;
        $this->adminId = $adminId;
        $this->message = $message;
        $this->timestamp = current_time('mysql', 1);
    }
}
