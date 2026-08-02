<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Order\OrderCancelled;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث إلغاء الطلب ويُخطر البائع
 */
class SendOrderCancelledNotificationListener implements ListenerInterface
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
        if (!$event instanceof OrderCancelled) {
            return;
        }

        try {
            $this->notificationService->sendOrderCancelledNotification(
                $event->parentOrderId,
                $event->vendorId
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار إلغاء الطلب', [
                'order_id'  => $event->parentOrderId,
                'vendor_id' => $event->vendorId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
