<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Withdrawal\WithdrawalApproved;
use VMP\Services\NotificationService;
use VMP\Core\Logger;

/**
 * يستمع لحدث الموافقة على السحب ويرسل إشعاراً للبائع
 */
class SendWithdrawalApprovedNotificationListener implements ListenerInterface
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
        if (!$event instanceof WithdrawalApproved) {
            return;
        }

        try {
            $this->notificationService->sendWithdrawalApprovedNotification(
                $event->withdrawalId,
                $event->vendorId,
                $event->amount
            );
        } catch (\Exception $e) {
            $this->logger->error('فشل إرسال إشعار الموافقة على السحب', [
                'withdrawal_id' => $event->withdrawalId,
                'vendor_id'     => $event->vendorId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
