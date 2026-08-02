<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Product\ProductApproved;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث الموافقة على منتج ويرسل إشعاراً للبائع
 */
class SendProductApprovedNotificationListener implements ListenerInterface
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
        if (!$event instanceof ProductApproved) {
            return;
        }

        try {
            $this->notificationService->sendProductApprovedNotification(
                $event->wcProductId,
                $event->vendorId
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار قبول المنتج', [
                'product_id' => $event->productId,
                'vendor_id'  => $event->vendorId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
