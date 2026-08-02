<?php
namespace VMP\Modules\VendorRegistration\Services;

interface NotificationServiceInterface
{
    /**
     * Notify via specified channels.
     *
     * @param array $channels list of channel keys to notify e.g. ['email','in_app','webhook']
     * @param array $payload structured payload passed to channels
     * @return bool true if all required channels were invoked successfully
     */
    public function notify(array $channels, array $payload): bool;
}
