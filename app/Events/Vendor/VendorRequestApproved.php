<?php
namespace VMP\Events\Vendor;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند الموافقة على طلب انضمام بائع
 */
class VendorRequestApproved extends AbstractEvent
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $vendorId,
        public readonly int $userId,
        public readonly string $storeName
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
        return 'vendor.request.approved';
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'request_id' => $this->requestId,
            'vendor_id'  => $this->vendorId,
            'user_id'    => $this->userId,
            'store_name' => $this->storeName,
        ]);
    }
}