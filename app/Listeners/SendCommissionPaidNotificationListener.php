<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Commission\CommissionPaid;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث دفع العمولة ويُرسل إشعاراً للبائع
 */
class SendCommissionPaidNotificationListener implements ListenerInterface
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
        if (!$event instanceof CommissionPaid) {
            return;
        }

        try {
            $this->notificationService->sendCommissionPaidNotification(
                $event->commissionId,
                $event->vendorId,
                $event->amount
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار دفع العمولة', [
                'commission_id' => $event->commissionId,
                'vendor_id'     => $event->vendorId,
                'amount'        => $event->amount,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
