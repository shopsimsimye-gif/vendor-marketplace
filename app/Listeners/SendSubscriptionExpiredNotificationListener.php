<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Subscription\SubscriptionExpired;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث انتهاء الاشتراك ويُرسل تنبيهاً للبائع
 */
class SendSubscriptionExpiredNotificationListener implements ListenerInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private Logger              $logger
    ) {}

    /**
     * Handle functionality helper.
     *
     * @param object $event Description index.
     * @return void Output payload.
     */
    public function handle(object $event): void
    {
        if (!$event instanceof SubscriptionExpired) {
            return;
        }

        try {
            $this->notificationService->sendSubscriptionExpiringNotification(
                $event->subscriptionId,
                $event->vendorId,
                $event->planName
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار انتهاء الاشتراك', [
                'subscription_id' => $event->subscriptionId,
                'vendor_id'       => $event->vendorId,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
