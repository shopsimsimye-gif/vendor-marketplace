<?php
namespace VMP\Jobs;

defined('ABSPATH') || exit;

use VMP\Core\Queue\JobInterface;
use VMP\Core\Container;
use VMP\Contracts\SubscriptionRepositoryInterface;
use VMP\Events\Subscription\SubscriptionExpired;
use VMP\Core\EventManager;
use VMP\Core\Logger;

/**
 * Class CheckExpiredSubscriptionsJob
 *
 * يفحص جميع الاشتراكات النشطة ويُنهي المنتهية الصلاحية
 * ويُطلق حدث SubscriptionExpired لكل اشتراك منتهٍ
 */
class CheckExpiredSubscriptionsJob implements JobInterface
{
    public function __construct(
        private int $batchSize = 50
    ) {}

    /**
     * Handle functionality helper.
     *
     * @return void Output payload.
     */
    public function handle(): void
    {
        $container   = Container::getInstance();
        $repo        = $container->make(SubscriptionRepositoryInterface::class);
        $events      = $container->make(EventManager::class);
        $logger      = $container->make(Logger::class);

        // جلب الاشتراكات المنتهية
        $expired = $repo->getExpired($this->batchSize);

        foreach ($expired as $subscription) {
            try {
                // إنهاء الاشتراك
                $repo->updateStatus($subscription->id, 'expired');

                // إطلاق الحدث
                $events->dispatch(new SubscriptionExpired(
                    subscriptionId: (int) $subscription->id,
                    vendorId:       (int) $subscription->vendor_id,
                    planId:         (int) $subscription->plan_id,
                    planName:       (string) ($subscription->plan_name ?? '')
                ));

                $logger->info("انتهى اشتراك #{$subscription->id} للبائع #{$subscription->vendor_id}");
            } catch (\Throwable $e) {
                $logger->error("فشل إنهاء الاشتراك #{$subscription->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * GetPayload functionality helper.
     *
     * @return array Output payload.
     */
    public function getPayload(): array
    {
        return [
            'batch_size' => $this->batchSize,
        ];
    }

    /**
     * FromPayload functionality helper.
     *
     * @param array $payload Description index.
     * @return self Output payload.
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            batchSize: (int) ($payload['batch_size'] ?? 50)
        );
    }
}
