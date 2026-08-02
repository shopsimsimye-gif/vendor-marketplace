<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Subscription\SubscriptionActivated;
use VMP\Core\Queue\QueueManager;
use VMP\Jobs\GenerateStatisticsJob;
use VMP\Core\Logger;

/**
 * يستمع لحدث تفعيل الاشتراك ويحدث إحصائيات البائع في الخلفية
 */
class UpdateStatisticsOnSubscriptionActivatedListener implements ListenerInterface
{
    public function __construct(
        private QueueManager $queueManager,
        private Logger       $logger
    ) {}

    /**
     * Handle functionality helper.
     *
     * @param object $event Description index.
     * @return void Output payload.
     */
    public function handle(object $event): void
    {
        if (!$event instanceof SubscriptionActivated) {
            return;
        }

        try {
            $this->queueManager->push(GenerateStatisticsJob::class, [
                'vendor_id' => $event->vendorId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('فشل جدولة تحديث إحصائيات البائع بعد تفعيل الاشتراك', [
                'vendor_id'       => $event->vendorId,
                'subscription_id' => $event->subscriptionId,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
