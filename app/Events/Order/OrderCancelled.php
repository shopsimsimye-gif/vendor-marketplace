<?php
namespace VMP\Events\Order;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند إلغاء طلب (واسترجاع الأرباح إن لزم)
 */
class OrderCancelled extends AbstractEvent
{
    public function __construct(
        public readonly int   $vendorOrderId,
        public readonly int   $parentOrderId,
        public readonly int   $vendorId,
        public readonly bool  $wasCompleted
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
        return 'order.cancelled';
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
            'was_completed'   => $this->wasCompleted,
        ]);
    }
}
