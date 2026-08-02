<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Events\VendorApproved;

class NotificationListener
{
    public function __invoke(VendorApproved $event): void
    {
        // Placeholder: send additional notifications (e.g., Slack, admin dashboard)
        // For now, we send an admin email notifying approval
        $admins = get_users(['role__in' => ['administrator', 'shop_manager']]);
        foreach ($admins as $admin) {
            if (user_can($admin, 'manage_vmp_requests')) {
                wp_mail($admin->user_email, __('Vendor approved', 'vmp'), sprintf(__('Vendor request %s was approved by user #%d', 'vmp'), $event->request->id ?? 'N/A', $event->approvedBy));
            }
        }
    }
}
