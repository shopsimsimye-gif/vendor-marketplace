<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع للموافقة على طلب انضمام بائع ويرسل الإشعارات
 */
class SendVendorRequestApprovedNotificationListener implements ListenerInterface
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
        if (!$event instanceof VendorRequestApproved) {
            return;
        }

        try {
            $this->notificationService->sendVendorRequestApprovedNotification(
                $event->requestId
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار قبول طلب البائع', [
                'request_id' => $event->requestId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}