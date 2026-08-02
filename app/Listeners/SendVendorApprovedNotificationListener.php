<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Vendor\VendorApproved;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث الموافقة على بائع ويرسل إشعاراً تهنئة للبائع
 */
class SendVendorApprovedNotificationListener implements ListenerInterface
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
        if (!$event instanceof VendorApproved) {
            return;
        }

        try {
            $this->notificationService->sendVendorApprovedNotification($event->vendorId);
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار قبول البائع', [
                'vendor_id' => $event->vendorId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
