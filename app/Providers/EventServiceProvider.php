<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Core\EventManager;
use VMP\Core\Logger;
use VMP\Events\CommissionPaid;
use VMP\Events\OrderCancelled;
use VMP\Events\OrderCompleted;
use VMP\Events\OrderPlaced;
use VMP\Events\ProductApproved;
use VMP\Events\SubscriptionActivated;
use VMP\Events\SubscriptionExpired;
use VMP\Events\VendorApproved;
use VMP\Events\VendorRegistered;
use VMP\Events\VendorRejected;
use VMP\Events\VendorRequestApproved;
use VMP\Events\VendorRequestRejected;
use VMP\Events\VendorRequestSubmitted;
use VMP\Events\WithdrawalApproved;
use VMP\Listeners\SendCommissionPaidNotificationListener;
use VMP\Listeners\SendOrderCancelledNotificationListener;
use VMP\Listeners\SendOrderPlacedNotificationListener;
use VMP\Listeners\SendProductApprovedNotificationListener;
use VMP\Listeners\SendSubscriptionExpiredNotificationListener;
use VMP\Listeners\SendVendorApprovedNotificationListener;
use VMP\Listeners\SendVendorRegisteredNotificationListener;
use VMP\Listeners\SendVendorRejectedNotificationListener;
use VMP\Listeners\SendVendorRequestApprovedNotificationListener;
use VMP\Listeners\SendVendorRequestRejectedNotificationListener;
use VMP\Listeners\SendVendorRequestSubmittedNotificationListener;
use VMP\Listeners\SendWithdrawalApprovedNotificationListener;
use VMP\Listeners\UpdateStatisticsOnSubscriptionActivatedListener;
use VMP\Listeners\UpdateVendorStatisticsOnOrderCompletedListener;
use VMP\Services\NotificationService;

/**
 * EventServiceProvider — ربط Events بالListeners
 *
 * @package VMP\Providers
 * @since 1.0.0
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        $this->container->singleton(
            NotificationService::class,
            fn(): NotificationService => new NotificationService(
                $this->container->make(\VMP\Contracts\VendorRepositoryInterface::class),
                $this->container->make(\VMP\Contracts\VendorRequestRepositoryInterface::class),
                $this->container->make(\VMP\Core\Queue\QueueManager::class)
            )
        );
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        /** @var EventManager $events */
        $events = $this->container->make(EventManager::class);

        /** @var NotificationService $notifications */
        $notifications = $this->container->make(NotificationService::class);

        /** @var Logger $logger */
        $logger = $this->container->make(Logger::class);

        /** @var \VMP\Core\Queue\QueueManager $queue */
        $queue = $this->container->make(\VMP\Core\Queue\QueueManager::class);

        // ─── Vendor Events ──────────────────────────────────────────────────
        $events->on(
            VendorRegistered::class,
            function ($event) use ($notifications, $logger) {
                (new SendVendorRegisteredNotificationListener($notifications, $logger))->handle($event);
            }
        );

        $events->on(
            VendorApproved::class,
            function ($event) use ($notifications, $logger) {
                (new SendVendorApprovedNotificationListener($notifications, $logger))->handle($event);
            }
        );

        $events->on(
            VendorRejected::class,
            function ($event) use ($notifications, $logger) {
                (new SendVendorRejectedNotificationListener($notifications, $logger))->handle($event);
            }
        );

        $events->on(
            VendorRequestSubmitted::class,
            function ($event) use ($notifications, $logger) {
                (new SendVendorRequestSubmittedNotificationListener($notifications, $logger))->handle($event);
            }
        );

        $events->on(
            VendorRequestApproved::class,
            function ($event) use ($notifications, $logger) {
                (new SendVendorRequestApprovedNotificationListener($notifications, $logger))->handle($event);
            }
        );

        $events->on(
            VendorRequestRejected::class,
            function ($event) use ($notifications, $logger) {
                (new SendVendorRequestRejectedNotificationListener($notifications, $logger))->handle($event);
            }
        );

        // ─── Order Events ───────────────────────────────────────────────────
        $events->on(
            OrderPlaced::class,
            function ($event) use ($notifications, $logger) {
                (new SendOrderPlacedNotificationListener($notifications, $logger))->handle($event);
            }
        );

        $events->on(
            OrderCompleted::class,
            function ($event) use ($queue, $logger) {
                (new UpdateVendorStatisticsOnOrderCompletedListener($queue, $logger))->handle($event);
            }
        );

        $events->on(
            OrderCancelled::class,
            function ($event) use ($notifications, $logger) {
                (new SendOrderCancelledNotificationListener($notifications, $logger))->handle($event);
            }
        );

        // ─── Product Events ─────────────────────────────────────────────────
        $events->on(
            ProductApproved::class,
            function ($event) use ($notifications, $logger) {
                (new SendProductApprovedNotificationListener($notifications, $logger))->handle($event);
            }
        );

        // ─── Withdrawal Events ──────────────────────────────────────────────
        $events->on(
            WithdrawalApproved::class,
            function ($event) use ($notifications, $logger) {
                (new SendWithdrawalApprovedNotificationListener($notifications, $logger))->handle($event);
            }
        );

        // ─── Subscription Events ────────────────────────────────────────────
        $events->on(
            SubscriptionActivated::class,
            function ($event) use ($queue, $logger) {
                (new UpdateStatisticsOnSubscriptionActivatedListener($queue, $logger))->handle($event);
            }
        );

        $events->on(
            SubscriptionExpired::class,
            function ($event) use ($notifications, $logger) {
                (new SendSubscriptionExpiredNotificationListener($notifications, $logger))->handle($event);
            }
        );

        // ─── Commission Events ──────────────────────────────────────────────
        $events->on(
            CommissionPaid::class,
            function ($event) use ($notifications, $logger) {
                (new SendCommissionPaidNotificationListener($notifications, $logger))->handle($event);
            }
        );
    }
}
