<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Order\OrderPlaced;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث وضع طلب ويرسل إشعار للبائع
 */
class SendOrderPlacedNotificationListener implements ListenerInterface
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
        if (!$event instanceof OrderPlaced) {
            return;
        }

        try {
            $this->notificationService->sendOrderPlacedNotification(
                $event->parentOrderId,
                $event->vendorId
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار الطلب الجديد', [
                'vendor_order_id' => $event->vendorOrderId,
                'vendor_id'       => $event->vendorId,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
