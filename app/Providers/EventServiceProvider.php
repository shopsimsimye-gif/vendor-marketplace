<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Core\EventManager;
use VMP\Core\Logger;
use VMP\Services\NotificationService;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\VendorRequestRepositoryInterface;

// Events
use VMP\Events\Vendor\VendorRegistered;
use VMP\Events\Vendor\VendorApproved;
use VMP\Events\Vendor\VendorRejected;
use VMP\Events\Vendor\VendorRequestSubmitted;
use VMP\Events\Vendor\VendorRequestApproved;
use VMP\Events\Vendor\VendorRequestRejected;
use VMP\Events\Order\OrderPlaced;
use VMP\Events\Order\OrderCompleted;
use VMP\Events\Order\OrderCancelled;
use VMP\Events\Product\ProductApproved;
use VMP\Events\Withdrawal\WithdrawalApproved;
use VMP\Events\Subscription\SubscriptionActivated;
use VMP\Events\Subscription\SubscriptionExpired;
use VMP\Events\Commission\CommissionPaid;

// Listeners
use VMP\Listeners\SendVendorRegisteredNotificationListener;
use VMP\Listeners\SendVendorApprovedNotificationListener;
use VMP\Listeners\SendVendorRejectedNotificationListener;
use VMP\Listeners\SendVendorRequestSubmittedNotificationListener;
use VMP\Listeners\SendVendorRequestApprovedNotificationListener;
use VMP\Listeners\SendVendorRequestRejectedNotificationListener;
use VMP\Listeners\SendOrderPlacedNotificationListener;
use VMP\Listeners\SendOrderCancelledNotificationListener;
use VMP\Listeners\UpdateVendorStatisticsOnOrderCompletedListener;
use VMP\Listeners\SendProductApprovedNotificationListener;
use VMP\Listeners\SendWithdrawalApprovedNotificationListener;
use VMP\Listeners\UpdateStatisticsOnSubscriptionActivatedListener;
use VMP\Listeners\SendSubscriptionExpiredNotificationListener;
use VMP\Listeners\SendCommissionPaidNotificationListener;

/**
 * EventServiceProvider — يسجّل ربط الأحداث بالمستمعين
 *
 * خريطة الأحداث:
 * - VendorRegistered     → SendVendorRegisteredNotificationListener
 * - VendorApproved       → SendVendorApprovedNotificationListener
 * - VendorRejected       → SendVendorRejectedNotificationListener
 * - OrderPlaced          → SendOrderPlacedNotificationListener
 * - OrderCompleted       → UpdateVendorStatisticsOnOrderCompletedListener
 * - OrderCancelled       → SendOrderCancelledNotificationListener
 * - ProductApproved      → SendProductApprovedNotificationListener
 * - WithdrawalApproved   → SendWithdrawalApprovedNotificationListener
 * - SubscriptionActivated→ UpdateStatisticsOnSubscriptionActivatedListener
 * - SubscriptionExpired  → SendSubscriptionExpiredNotificationListener
 * - CommissionPaid       → SendCommissionPaidNotificationListener
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
        // NotificationService
        $this->container->singleton(
            NotificationService::class,
            fn(): NotificationService => new NotificationService(
                $this->container->make(VendorRepositoryInterface::class),
                $this->container->make(VendorRequestRepositoryInterface::class),
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
            [new SendVendorRegisteredNotificationListener($notifications, $logger), 'handle']
        );

        $events->on(
            VendorApproved::class,
            [new SendVendorApprovedNotificationListener($notifications, $logger), 'handle']
        );

        $events->on(
            VendorRejected::class,
            [new SendVendorRejectedNotificationListener($notifications, $logger), 'handle']
        );

        $events->on(
            VendorRequestSubmitted::class,
            [new SendVendorRequestSubmittedNotificationListener($notifications, $logger), 'handle']
        );

        $events->on(
            VendorRequestApproved::class,
            [new SendVendorRequestApprovedNotificationListener($notifications, $logger), 'handle']
        );

        $events->on(
            VendorRequestRejected::class,
            [new SendVendorRequestRejectedNotificationListener($notifications, $logger), 'handle']
        );

        // ─── Order Events ───────────────────────────────────────────────────
        $events->on(
            OrderPlaced::class,
            [new SendOrderPlacedNotificationListener($notifications, $logger), 'handle']
        );

        $events->on(
            OrderCompleted::class,
            [new UpdateVendorStatisticsOnOrderCompletedListener($queue, $logger), 'handle']
        );

        $events->on(
            OrderCancelled::class,
            [new SendOrderCancelledNotificationListener($notifications, $logger), 'handle']
        );

        // ─── Product Events ─────────────────────────────────────────────────
        $events->on(
            ProductApproved::class,
            [new SendProductApprovedNotificationListener($notifications, $logger), 'handle']
        );

        // ─── Withdrawal Events ──────────────────────────────────────────────
        $events->on(
            WithdrawalApproved::class,
            [new SendWithdrawalApprovedNotificationListener($notifications, $logger), 'handle']
        );

        // ─── Subscription Events ────────────────────────────────────────────
        $events->on(
            SubscriptionActivated::class,
            [new UpdateStatisticsOnSubscriptionActivatedListener($queue, $logger), 'handle']
        );

        $events->on(
            SubscriptionExpired::class,
            [new SendSubscriptionExpiredNotificationListener($notifications, $logger), 'handle']
        );

        // ─── Commission Events ──────────────────────────────────────────────
        $events->on(
            CommissionPaid::class,
            [new SendCommissionPaidNotificationListener($notifications, $logger), 'handle']
        );
    }
}
