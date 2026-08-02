<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

use VMP\Events\Order\OrderCompleted;
use VMP\Core\Queue\QueueManager;
use VMP\Jobs\GenerateStatisticsJob;
use VMP\Core\Logger;

/**
 * يستمع لحدث اكتمال الطلب ويقوم بجدولة عملية تحديث الإحصائيات في الخلفية
 */
class UpdateVendorStatisticsOnOrderCompletedListener implements ListenerInterface
{
    public function __construct(
        private QueueManager $queueManager,
        private Logger       $logger
    ) {}

    /**
     * Handle functionality helper.
     *
     * @param object $event Description index.
     * @return void Output payload.
     */
    public function handle(object $event): void
    {
        if (!$event instanceof OrderCompleted) {
            return;
        }

        try {
            $this->queueManager->push(GenerateStatisticsJob::class, [
                'vendor_id' => $event->vendorId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('فشل جدولة عملية تحديث إحصائيات البائع عند اكتمال الطلب', [
                'vendor_id'       => $event->vendorId,
                'vendor_order_id' => $event->vendorOrderId,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
