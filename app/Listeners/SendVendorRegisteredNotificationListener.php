<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Vendor\VendorRegistered;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث تسجيل بائع ويرسل الإشعارات للمشرف والبائع
 */
class SendVendorRegisteredNotificationListener implements ListenerInterface
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
        if (!$event instanceof VendorRegistered) {
            return;
        }

        try {
            $this->notificationService->sendVendorRegisteredNotification(
                $event->vendorId,
                $event->userId
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار تسجيل البائع', [
                'vendor_id' => $event->vendorId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
