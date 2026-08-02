<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Events\VendorApproved;

class RedirectAfterLoginListener
{
    public function __invoke(VendorApproved $event): void
    {
        $userId = $event->request->user_id ?? 0;
        if (!$userId) return;
        // store a user meta flag so after next login we can redirect
        update_user_meta($userId, 'vmp_redirect_after_login', '/vendor/store/setup');
    }
}
