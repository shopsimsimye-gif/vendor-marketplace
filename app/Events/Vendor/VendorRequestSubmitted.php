<?php
namespace VMP\Events\Vendor;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند تقديم طلب انضمام بائع جديد (حالة انتظار)
 */
class VendorRequestSubmitted extends AbstractEvent
{
    public function __construct(
        public readonly int $requestId,
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
        return 'vendor.request.submitted';
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'request_id'  => $this->requestId,
            'user_id'     => $this->userId,
            'store_name'  => $this->storeName,
            'email'       => $this->storeEmail,
        ]);
    }
}