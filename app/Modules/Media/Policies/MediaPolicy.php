<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Policies;

defined('ABSPATH') || exit;

class MediaPolicy
{
    public function canView(int $userId, int $vendorId): bool
    {
        return $userId === $vendorId || current_user_can('manage_options');
    }

    public function canUpload(int $userId, int $vendorId): bool
    {
        return $userId === $vendorId || current_user_can('manage_options');
    }

    public function canDelete(int $userId, int $vendorId): bool
    {
        return $userId === $vendorId || current_user_can('manage_options');
    }

    public function canManageFolders(int $userId, int $vendorId): bool
    {
        return $userId === $vendorId || current_user_can('manage_options');
    }

    public function canManageStorage(int $userId, int $vendorId): bool
    {
        return $userId === $vendorId || current_user_can('manage_options');
    }

    public function canAccessMedia(int $userId, int $mediaVendorId): bool
    {
        return $userId === $mediaVendorId || current_user_can('manage_options');
    }

    public function isVendor(int $userId): bool
    {
        return user_can($userId, 'vmp_vendor') || current_user_can('manage_options');
    }
}
