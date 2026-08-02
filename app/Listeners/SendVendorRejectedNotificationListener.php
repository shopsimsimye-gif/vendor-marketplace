<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Vendor\VendorRejected;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث رفض البائع ويرسل إشعاراً بالرفض مع السبب
 */
class SendVendorRejectedNotificationListener implements ListenerInterface
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
        if (!$event instanceof VendorRejected) {
            return;
        }

        try {
            $this->notificationService->sendVendorRejectedNotification(
                $event->vendorId,
                $event->reason
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار رفض البائع', [
                'vendor_id' => $event->vendorId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
