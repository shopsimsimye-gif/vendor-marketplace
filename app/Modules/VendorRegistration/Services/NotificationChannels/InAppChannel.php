<?php
namespace VMP\Modules\VendorRegistration\Services\NotificationChannels;

class InAppChannel
{
    /**
     * $payload keys: user_id, title, body, data (array)
     */
    public function __invoke(array $payload): bool
    {
        // simple do_action to allow other parts to store notification to DB / show in UI
        do_action('vmp_create_in_app_notification', $payload);
        return true;
    }
}
