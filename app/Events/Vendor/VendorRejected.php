<?php
namespace VMP\Events\Vendor;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند رفض طلب بائع من قِبَل المشرف
 */
class VendorRejected extends AbstractEvent
{
    public function __construct(
        public readonly int    $vendorId,
        public readonly int    $userId,
        public readonly string $storeName,
        public readonly string $storeEmail,
        public readonly string $reason = ''
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
        return 'vendor.rejected';
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
            'reason'     => $this->reason,
        ]);
    }
}
