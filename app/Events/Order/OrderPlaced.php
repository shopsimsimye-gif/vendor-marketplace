<?php
namespace VMP\Events\Order;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند إنشاء طلب فرعي لبائع بعد وضع طلب WooCommerce
 */
class OrderPlaced extends AbstractEvent
{
    public function __construct(
        public readonly int   $vendorOrderId,
        public readonly int   $parentOrderId,
        public readonly int   $vendorId,
        public readonly float $total,
        public readonly float $vendorEarnings,
        public readonly float $commission
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
        return 'order.placed';
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'vendor_order_id' => $this->vendorOrderId,
            'parent_order_id' => $this->parentOrderId,
            'vendor_id'       => $this->vendorId,
            'total'           => $this->total,
            'vendor_earnings' => $this->vendorEarnings,
            'commission'      => $this->commission,
        ]);
    }
}
