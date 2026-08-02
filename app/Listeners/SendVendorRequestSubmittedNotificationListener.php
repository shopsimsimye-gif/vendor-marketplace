<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لتقديم طلب انضمام بائع جديد ويرسل الإشعارات
 */
class SendVendorRequestSubmittedNotificationListener implements ListenerInterface
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
        if (!$event instanceof VendorRequestSubmitted) {
            return;
        }

        try {
            $this->notificationService->sendVendorRequestSubmittedNotification(
                $event->requestId
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار تقديم طلب البائع', [
                'request_id' => $event->requestId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}