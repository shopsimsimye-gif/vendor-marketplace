<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\CommissionRepositoryInterface;
use VMP\Contracts\SubscriptionPlanRepositoryInterface;
use VMP\Contracts\SubscriptionRepositoryInterface;
use VMP\Core\EventManager;
use VMP\Core\Logger;
use Exception;

/**
 * Class CommissionService
 *
 * Description of administrative platform component CommissionService.
 *
 * @package vendor-marketplace
 */
class CommissionService
{
    public function __construct(
        private CommissionRepositoryInterface $commissionRepository,
        private SubscriptionPlanRepositoryInterface $planRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private EventManager $eventManager,
        private Logger $logger
    ) {}

    /**
     * حساب نسبة العمولة لبائع معين بناءً على خطة اشتراكه
     *
     * @param int $vendorId
     * @return float
     */
    public function calculateRate(int $vendorId): float
    {
        $activeSubscription = $this->subscriptionRepository->findActiveByVendor($vendorId);
        if ($activeSubscription) {
            $plan = $this->planRepository->find((int) $activeSubscription->plan_id);
            if ($plan) {
                return (float) $plan->commission_rate;
            }
        }

        $freePlan = $this->planRepository->findBySlug('free');
        if ($freePlan) {
            return (float) $freePlan->commission_rate;
        }

        return (float) get_option('vmp_default_commission', 10);
    }

    /**
     * حساب قيمة العمولة ومستحقات البائع بناءً على إجمالي المبلغ والنسبة
     *
     * @param float $total
     * @param float $rate
     * @return array ['rate', 'commission_amount', 'vendor_amount']
     */
    public function calculateAmount(float $total, float $rate): array
    {
        $commissionAmount = round(($total * $rate) / 100, 2);
        $vendorAmount = round($total - $commissionAmount, 2);
        return [
            'rate' => $rate,
            'commission_amount' => $commissionAmount,
            'vendor_amount' => $vendorAmount,
        ];
    }

    /**
     * دفع عمولة محددة (تغيير حالتها إلى paid)
     *
     * @param int $commissionId
     * @return void
     * @throws Exception
     */
    public function payCommission(int $commissionId): void
    {
        $commission = $this->commissionRepository->find($commissionId);

        if (!$commission) {
            throw new Exception(__('العمولة غير موجودة', 'vmp'));
        }
        if ($commission->status === 'paid') {
            throw new Exception(__('تم دفع هذه العمولة مسبقاً', 'vmp'));
        }

        if ($this->commissionRepository->markAsPaid($commissionId)) {
            $this->logger->info('تم دفع عمولة', [
                'commission_id' => $commissionId,
                'vendor_id' => $commission->vendor_id,
                'amount' => $commission->commission_amount,
            ]);

            try {
                $this->eventManager->trigger(
                    'vmp_commission_paid',
                    $commissionId,
                    (int) $commission->vendor_id,
                    (float) $commission->commission_amount
                );
            } catch (Exception $e) {
                $this->logger->error('فشل إطلاق حدث دفع العمولة: ' . $e->getMessage());
            }
        } else {
            throw new Exception(__('حدث خطأ أثناء محاولة الدفع', 'vmp'));
        }
    }

    /**
     * دفع عمولات متعددة دفعة واحدة
     *
     * @param array $ids
     * @return int عدد العمولات التي تم دفعها
     * @throws Exception
     */
    public function bulkPayCommissions(array $ids): int
    {
        if (empty($ids)) {
            throw new Exception(__('لم يتم تحديد أي عمولات', 'vmp'));
        }

        $count = $this->commissionRepository->markBulkAsPaid($ids);
        
        if ($count > 0) {
            $this->logger->info("تم دفع {$count} عمولة دفعياً", ['ids' => $ids]);
        }

        return $count;
    }

    /**
     * الحصول على إحصائيات عامة للعمولات (للمشرف)
     *
     * @return array
     */
    public function getAdminStats(): array
    {
        return $this->commissionRepository->getAdminStats();
    }

    /**
     * جلب العمولات المعلقة (للمشرف)
     *
     * @param int $limit
     * @return array
     */
    public function getPendingCommissions(int $limit = 50): array
    {
        return $this->commissionRepository->getAllPending($limit);
    }

    /**
     * جلب عمولات بائع معين (للبائع)
     *
     * @param int $vendorId
     * @param array $args
     * @return array
     */
    public function getVendorCommissions(int $vendorId, array $args = []): array
    {
        return $this->commissionRepository->getByVendor($vendorId, $args);
    }

    /**
     * جلب الإحصائيات الشهرية لبائع معين (للرسم البياني)
     *
     * @param int $vendorId
     * @param int $months
     * @return array
     */
    public function getVendorChartStats(int $vendorId, int $months = 12): array
    {
        return $this->commissionRepository->getMonthlyStats($vendorId, $months);
    }
}
