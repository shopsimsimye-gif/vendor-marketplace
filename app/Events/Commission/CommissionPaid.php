<?php
namespace VMP\Events\Commission;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند دفع عمولة لبائع
 */
class CommissionPaid extends AbstractEvent
{
    public function __construct(
        public readonly int   $commissionId,
        public readonly int   $vendorId,
        public readonly float $amount,
        public readonly int   $orderId
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
        return 'commission.paid';
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'commission_id' => $this->commissionId,
            'vendor_id'     => $this->vendorId,
            'amount'        => $this->amount,
            'order_id'      => $this->orderId,
        ]);
    }
}
