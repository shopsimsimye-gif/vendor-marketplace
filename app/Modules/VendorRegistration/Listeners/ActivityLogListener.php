<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Events\VendorApproved;

class ActivityLogListener
{
    public function __invoke(VendorApproved $event): void
    {
        // log to vendor_request_logs table via repository
        $requestObj = $event->request;
        if (empty($requestObj->id)) return;
        $repo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository();
        $repo->logTransition((int)$requestObj->id, $requestObj->status ?? '', 'approved', $event->approvedBy, 'Approved by admin');
    }
}
