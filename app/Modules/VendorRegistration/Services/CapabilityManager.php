<?php
namespace VMP\Modules\VendorRegistration\Services;

use VMP\Modules\VendorRegistration\Config\Capabilities;

class CapabilityManager
{
    /**
     * Ensure roles have the required capabilities. Safe to call repeatedly.
     */
    public static function register(): void
    {
        $roles = [
            'administrator',
        ];

        foreach ($roles as $roleName) {
            $role = get_role($roleName);
            if ($role && ! $role->has_cap(Capabilities::MANAGE_VENDOR_REQUESTS)) {
                $role->add_cap(Capabilities::MANAGE_VENDOR_REQUESTS);
            }
        }
    }
}
