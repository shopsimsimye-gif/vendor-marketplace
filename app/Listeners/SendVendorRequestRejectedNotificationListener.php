<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لرفض طلب انضمام بائع ويرسل الإشعارات
 */
class SendVendorRequestRejectedNotificationListener implements ListenerInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private Logger              $logger
    ) {}

    /**
     * Handle functionality helper.
     *
     * @param object $event وصف
     * @return void
     */
    public function handle(object $event): void
    {
        if (!$event instanceof VendorRequestRejected) {
            return;
        }

        try {
            $this->notificationService->sendVendorRequestRejectedNotification(
                $event->requestId,
                $event->reason
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار رفض طلب البائع', [
                'request_id' => $event->requestId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}