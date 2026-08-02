<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\SubscriptionRepositoryInterface;
use VMP\Contracts\SubscriptionPlanRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\ProductRepositoryInterface;
use VMP\Core\EventManager;
use VMP\Core\Logger;
use Exception;

/**
 * Class SubscriptionService
 *
 * Description of administrative platform component SubscriptionService.
 *
 * @package vendor-marketplace
 */
class SubscriptionService
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SubscriptionPlanRepositoryInterface $planRepository,
        private VendorRepositoryInterface $vendorRepository,
        private ProductRepositoryInterface $productRepository,
        private EventManager $eventManager,
        private Logger $logger
    ) {}

    /**
     * التحقق مما إذا كان البائع لديه طلب تغيير خطة معلق
     *
     * @param int $vendorId
     * @return bool
     */
    public function hasPendingPlanChange(int $vendorId): bool
    {
        $pending = $this->subscriptionRepository->getPendingPlanChangeByVendor($vendorId);
        return $pending !== null;
    }

    /**
     * التحقق من إمكانية إضافة منتج بناءً على الخطة الحالية والطلبات المعلقة
     *
     * @param int $vendorId
     * @return bool
     */
    public function canAddProduct(int $vendorId): bool
    {
        if ($this->hasPendingPlanChange($vendorId)) {
            return false;
        }

        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            return false;
        }

        $activeSubscription = $this->subscriptionRepository->findActiveByVendor($vendorId);
        $currentCount = $this->productRepository->countByVendor($vendorId);

        if (!$activeSubscription) {
            $freePlan = $this->planRepository->findBySlug('free');
            if (!$freePlan) {
                return $currentCount < 10;
            }
            return $this->planRepository->canAddProduct((int) $freePlan->id, $currentCount);
        }

        $plan = $this->planRepository->find((int) $activeSubscription->plan_id);
        if (!$plan) {
            return false;
        }

        return $this->planRepository->canAddProduct((int) $plan->id, $currentCount);
    }

    /**
     * التحقق من توفر ميزة معينة ضمن خطة البائع
     *
     * @param int $vendorId
     * @param string $feature
     * @return bool
     */
    public function hasFeature(int $vendorId, string $feature): bool
    {
        $active = $this->subscriptionRepository->findActiveByVendor($vendorId);
        $plan = $active
            ? $this->planRepository->find((int) $active->plan_id)
            : $this->planRepository->findBySlug('free');

        if (!$plan) {
            return false;
        }

        $features = $this->planRepository->getFeatures((int) $plan->id);
        return !empty($features[$feature]);
    }

    /**
     * تعيين الخطة المجانية الافتراضية للبائع عند اعتماده
     *
     * @param int $vendorId
     * @return void
     */
    public function assignFreePlan(int $vendorId): void
    {
        $freePlan = $this->planRepository->findBySlug('free');
        if (!$freePlan) {
            return;
        }

        if ($this->subscriptionRepository->findActiveByVendor($vendorId)) {
            return;
        }

        $startDate = current_time('mysql');
        $endDate = date('Y-m-d H:i:s', strtotime('+10 years'));

        $this->subscriptionRepository->create([
            'vendor_id' => $vendorId,
            'plan_id' => (int) $freePlan->id,
            'status' => 'active',
            'amount' => 0,
            'billing_period' => $freePlan->billing_period,
            'billing_interval' => (int) $freePlan->billing_interval,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * اشتراك أو ترقية إلى خطة محددة
     *
     * @param int $vendorId
     * @param int $planId
     * @return int معرف الاشتراك
     * @throws Exception
     */
    public function subscribe(int $vendorId, int $planId): int
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor || $vendor->status !== 'approved') {
            throw new Exception(__('يجب أن تكون بائعاً معتمداً', 'vmp'));
        }

        $plan = $this->planRepository->find($planId);
        if (!$plan || !$plan->is_active) {
            throw new Exception(__('الخطة غير موجودة أو غير متاحة', 'vmp'));
        }

        $current = $this->subscriptionRepository->findActiveByVendor($vendorId);
        if ($current) {
            $this->subscriptionRepository->cancel((int) $current->id);
        }

        $startDate = current_time('mysql');
        $endDate = date('Y-m-d H:i:s', strtotime("+{$plan->billing_interval} {$plan->billing_period}"));

        $subscriptionId = $this->subscriptionRepository->create([
            'vendor_id' => $vendorId,
            'plan_id' => $planId,
            'status' => 'active',
            'amount' => (float) $plan->price,
            'billing_period' => $plan->billing_period,
            'billing_interval' => (int) $plan->billing_interval,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        if (!$subscriptionId) {
            throw new Exception(__('حدث خطأ أثناء الاشتراك', 'vmp'));
        }

        $this->vendorRepository->update($vendorId, [
            'subscription_plan' => $plan->slug,
            'subscription_status' => 'active',
            'subscription_start' => $startDate,
            'subscription_expiry' => $endDate,
        ]);

        try {
            $this->eventManager->trigger(
                'vmp_subscription_created',
                $subscriptionId,
                $vendorId,
                $planId
            );
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث الاشتراك: ' . $e->getMessage());
        }

        return $subscriptionId;
    }

    /**
     * إلغاء الاشتراك الحالي
     *
     * @param int $vendorId
     * @return void
     * @throws Exception
     */
    public function cancelSubscription(int $vendorId): void
    {
        $current = $this->subscriptionRepository->findActiveByVendor($vendorId);
        if (!$current) {
            throw new Exception(__('ليس لديك اشتراك نشط لإلغائه', 'vmp'));
        }

        $this->subscriptionRepository->cancel((int) $current->id);

        $this->vendorRepository->update($vendorId, [
            'subscription_plan' => 'free',
            'subscription_status' => 'cancelled',
            'subscription_expiry' => current_time('mysql'),
        ]);

        try {
            $this->eventManager->trigger(
                'vmp_subscription_cancelled',
                (int) $current->id,
                $vendorId
            );
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث إلغاء الاشتراك: ' . $e->getMessage());
        }
    }

    /**
     * مراجعة الاشتراكات المنتهية وإلغاؤها تلقائياً
     *
     * @return void
     */
    public function checkExpiredSubscriptions(): void
    {
        $expired = $this->subscriptionRepository->getExpired();
        foreach ($expired as $subscription) {
            $this->subscriptionRepository->cancel($subscription->id);
            $this->vendorRepository->update((int) $subscription->vendor_id, [
                'subscription_plan' => 'free',
                'subscription_status' => 'active',
                'subscription_expiry' => null,
            ]);

            $this->logger->info(
                'انتهى اشتراك البائع وتم إرجاعه للخطة المجانية',
                ['subscription_id' => $subscription->id, 'vendor_id' => $subscription->vendor_id]
            );

            try {
                $this->eventManager->trigger(
                    'vmp_subscription_expired',
                    (int) $subscription->id,
                    (int) $subscription->vendor_id
                );
            } catch (Exception $e) {
                // تجاهل
            }
        }
    }

    /**
     * إرسال تنبيهات باقتراب انتهاء الاشتراك
     *
     * @return void
     */
    public function sendReminders(): void
    {
        $expiring = $this->subscriptionRepository->getExpiringSoon(7);
        foreach ($expiring as $subscription) {
            try {
                $this->eventManager->trigger(
                    'vmp_subscription_expiring',
                    (int) $subscription->id,
                    (int) $subscription->vendor_id,
                    $subscription->end_date
                );
            } catch (Exception $e) {
                // تجاهل
            }
        }
    }

    /**
     * طلب تغيير خطة
     *
     * @param int $vendorId
     * @param int $newPlanId
     * @return int
     * @throws Exception
     */
    public function requestPlanChange(int $vendorId, int $newPlanId): int
    {
        if ($this->hasPendingPlanChange($vendorId)) {
            throw new Exception(__('لديك طلب تغيير خطة معلق حالياً', 'vmp'));
        }

        $plan = $this->planRepository->find($newPlanId);
        if (!$plan || !$plan->is_active) {
            throw new Exception(__('الخطة المطلوبة غير موجودة أو غير متاحة', 'vmp'));
        }

        $requestId = $this->subscriptionRepository->requestPlanChange($vendorId, $newPlanId);
        if (!$requestId) {
            throw new Exception(__('حدث خطأ أثناء تقديم طلب التغيير', 'vmp'));
        }

        // Notify the system (events + WP action) so admin notifications can be handled by listeners
        try {
            $this->eventManager->trigger('vmp_plan_change_requested', $requestId, $vendorId, $newPlanId);
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث طلب تغيير الخطة: ' . $e->getMessage());
        }

        // Also fire a WordPress action for backward compatibility / plugin hooks
        try {
            do_action('vmp_plan_change_requested', $requestId, $vendorId, $newPlanId);
        } catch (\Throwable $e) {
            $this->logger->error('فشل تنفيذ action vmp_plan_change_requested: ' . $e->getMessage());
        }

        return $requestId;
    }

    /**
     * الموافقة على طلب تغيير الخطة
     *
     * @param int $requestId
     * @return void
     * @throws Exception
     */
    public function approvePlanChange(int $requestId): void
    {
        $request = $this->subscriptionRepository->find($requestId);
        if (!$request || $request->status !== 'pending_change') {
            throw new Exception(__('الطلب غير موجود أو تمت معالجته', 'vmp'));
        }

        $success = $this->subscriptionRepository->approvePlanChange($requestId);
        if (!$success) {
            throw new Exception(__('حدث خطأ أثناء الموافقة', 'vmp'));
        }

        $plan = $this->planRepository->find((int) $request->plan_id);
        $this->vendorRepository->update((int) $request->vendor_id, [
            'subscription_plan' => $plan ? $plan->slug : '',
            'subscription_status' => 'active',
        ]);
        
        try {
            $this->eventManager->trigger(
                'vmp_plan_change_approved',
                $requestId,
                (int) $request->vendor_id,
                (int) $request->plan_id
            );
        } catch (Exception $e) {
            // تجاهل
        }
    }

    /**
     * رفض طلب تغيير الخطة
     *
     * @param int $requestId
     * @param string $reason
     * @return void
     * @throws Exception
     */
    public function rejectPlanChange(int $requestId, string $reason = ''): void
    {
        $request = $this->subscriptionRepository->find($requestId);
        if (!$request || $request->status !== 'pending_change') {
            throw new Exception(__('الطلب غير موجود أو تمت معالجته', 'vmp'));
        }

        $success = $this->subscriptionRepository->rejectPlanChange($requestId);
        if (!$success) {
            throw new Exception(__('حدث خطأ أثناء الرفض', 'vmp'));
        }

        try {
            $this->eventManager->trigger(
                'vmp_plan_change_rejected',
                $requestId,
                (int) $request->vendor_id,
                $reason
            );
        } catch (Exception $e) {
            // تجاهل
        }
    }

    /**
     * جلب طلب تغيير الخطة المعلق لبائع محدد
     *
     * @param int $vendorId
     * @return object|null
     */
    public function getPendingPlanChange(int $vendorId): ?object
    {
        return $this->subscriptionRepository->getPendingPlanChangeByVendor($vendorId);
    }

    /**
     * إلغاء طلب تغيير الخطة المعلق
     *
     * @param int $requestId
     * @return void
     * @throws Exception
     */
    public function cancelPendingPlanChange(int $requestId): void
    {
        if (!$this->subscriptionRepository->forceDelete($requestId)) {
            throw new Exception(__('حدث خطأ أثناء إلغاء الطلب.', 'vmp'));
        }
    }

    /**
     * جلب تفاصيل اشتراك بائع للمشرف
     *
     * @param int $vendorId
     * @return array
     */
    public function getVendorSubscriptionDetails(int $vendorId): array
    {
        $active = $this->subscriptionRepository->findActiveByVendor($vendorId);
        $plan = $active ? $this->planRepository->find((int) $active->plan_id) : null;
        return [
            'subscription' => $active,
            'plan'         => $plan,
        ];
    }

    /**
     * جلب جميع طلبات تغيير الخطة المعلقة للمشرف
     *
     * @return array
     */
    public function getAllPendingPlanChanges(): array
    {
        return $this->subscriptionRepository->getPendingPlanChanges();
    }
}
