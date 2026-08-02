<?php
namespace VMP\Events\Vendor;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند رفض طلب انضمام بائع
 */
class VendorRequestRejected extends AbstractEvent
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $userId,
        public readonly string $reason,
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
        return 'vendor.request.rejected';
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
            'user_id'    => $this->userId,
            'reason'     => $this->reason,
            'store_name' => $this->storeName,
        ]);
    }
}