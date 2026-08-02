<?php
namespace VMP\Events\Subscription;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند انتهاء صلاحية اشتراك بائع
 */
class SubscriptionExpired extends AbstractEvent
{
    public function __construct(
        public readonly int    $subscriptionId,
        public readonly int    $vendorId,
        public readonly int    $planId,
        public readonly string $planName
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
        return 'subscription.expired';
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'subscription_id' => $this->subscriptionId,
            'vendor_id'       => $this->vendorId,
            'plan_id'         => $this->planId,
            'plan_name'       => $this->planName,
        ]);
    }
}
