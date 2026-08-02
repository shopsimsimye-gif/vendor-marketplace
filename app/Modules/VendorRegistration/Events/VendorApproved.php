<?php
namespace VMP\Modules\VendorRegistration\Events;

class VendorApproved
{
    public object $request; // vendor request object
    public int $approvedBy;

    public function __construct(object $request, int $approvedBy)
    {
        $this->request = $request;
        $this->approvedBy = $approvedBy;
    }
}
