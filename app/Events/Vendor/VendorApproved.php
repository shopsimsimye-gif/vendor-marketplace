<?php
namespace VMP\Events\Vendor;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند الموافقة على بائع من قِبَل المشرف
 */
class VendorApproved extends AbstractEvent
{
    public function __construct(
        public readonly int $vendorId,
        public readonly int $userId,
        public readonly string $storeName,
        public readonly string $storeEmail
    ) {
        parent::__construct();
    }

    /**
     * GetName functionality helper.
     *
     * @return string Output payload.
     */
    public function getName(): string
    {
        return 'vendor.approved';
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'vendor_id'  => $this->vendorId,
            'user_id'    => $this->userId,
            'store_name' => $this->storeName,
            'email'      => $this->storeEmail,
        ]);
    }
}
