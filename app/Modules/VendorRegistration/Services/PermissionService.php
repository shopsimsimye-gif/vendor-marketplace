<?php
namespace VMP\Modules\VendorRegistration\Services;

use VMP\Modules\VendorRegistration\Config\Capabilities;

class PermissionService
{
    /**
     * Centralized permission check for vendor reviews.
     *
     * Note: We DO NOT permanently fallback to 'manage_options'.
     * A temporary fallback to 'manage_options' is allowed only when
     * the capabilities version recorded in the database does not match the
     * code CAPABILITIES_VERSION (i.e. during an upgrade/migration window).
     */
    public static function canManageVendorRequests(): bool
    {
        // Allow super admins on multisite
        if (function_exists('is_multisite') && is_multisite() && function_exists('is_super_admin') && is_super_admin()) {
            return true;
        }

        // Primary check
        if (function_exists('current_user_can') && current_user_can(Capabilities::MANAGE_VENDOR_REQUESTS)) {
            return true;
        }

        // Temporary upgrade window: if capabilities version in options is outdated,
        // allow users with manage_options to access so administrators can complete
        // migration tasks. This is intentionally NOT a permanent fallback.
        if (function_exists('get_option')) {
            $current = get_option('vmp_capabilities_version');
            if ($current !== Capabilities::CAPABILITIES_VERSION) {
                if (function_exists('current_user_can') && current_user_can('manage_options')) {
                    return true;
                }
            }
        }

        return false;
    }
}
