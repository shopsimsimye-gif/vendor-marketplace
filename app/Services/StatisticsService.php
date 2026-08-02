<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\CommissionRepositoryInterface;
use VMP\Contracts\OrderRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\WithdrawalRepositoryInterface;

/**
 * Class StatisticsService
 *
 * Description of administrative platform component StatisticsService.
 *
 * @package vendor-marketplace
 */
class StatisticsService
{
    public function __construct(
        private CommissionRepositoryInterface $commissionRepository,
        private OrderRepositoryInterface $orderRepository,
        private VendorRepositoryInterface $vendorRepository,
        private WithdrawalRepositoryInterface $withdrawalRepository,
        private \wpdb $db
    ) {}

    /**
     * ملخص عام لأداء البائع
     */
    public function getVendorSummary(int $vendorId): array
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            return [];
        }

        return [
            'balance' => (float) $vendor->balance,
            'total_sales' => $this->orderRepository->getTotalSales($vendorId),
            'total_earnings' => $this->orderRepository->getTotalEarnings($vendorId),
            'total_orders' => $this->orderRepository->countByVendor($vendorId),
            'pending_orders' => $this->orderRepository->countByVendor($vendorId, 'pending'),
            'completed_orders' => $this->orderRepository->countByVendor($vendorId, 'completed'),
            'total_products' => (int) $vendor->total_products,
        ];
    }

    /**
     * تقرير العمولات والمبيعات لبائع خلال فترة زمنية
     */
    public function getVendorReport(int $vendorId, string $period = 'month'): array
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            return [];
        }

        $dateFrom = $this->getPeriodStart($period);
        $dateTo = current_time('mysql');

        $commissionStats = $this->commissionRepository->getTotalByVendorAndPeriod($vendorId, $dateFrom, $dateTo);

        return [
            'period' => $period,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'stats' => $commissionStats,
            'balance' => (float) $vendor->balance,
        ];
    }

    /**
     * بيانات الرسم البياني للبائع
     */
    public function getVendorChart(int $vendorId, int $months = 6): array
    {
        $months = max(1, min(24, $months));
        $monthly = $this->commissionRepository->getMonthlyStats($vendorId, $months);

        $labels = [];
        $earnings = [];
        $orders = [];

        foreach ($monthly as $row) {
            $labels[] = $this->formatMonthLabel($row->month);
            $earnings[] = (float) $row->earnings;
            $orders[] = (int) $row->orders;
        }

        return [
            'labels' => $labels,
            'earnings' => $earnings,
            'orders' => $orders,
        ];
    }

    /**
     * التقرير الشامل للمشرف (Admin)
     */
    public function getAdminReport(string $period = 'month'): array
    {
        $dateFrom = $this->getPeriodStart($period);

        $commissionStats = $this->commissionRepository->getAdminStats();
        $totalVendors = $this->vendorRepository->getCount();
        $activeVendors = $this->vendorRepository->getCount('approved');
        $pendingVendors = $this->vendorRepository->getCount('pending');

        $pendingWithdrawals = (float) $this->db->get_var(
            "SELECT COALESCE(SUM(amount), 0) FROM {$this->db->prefix}vmp_withdrawals WHERE status = 'pending'"
        );

        $activeSubscriptions = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->prefix}vmp_vendor_subscriptions WHERE status = 'active'"
        );

        return [
            'period' => $period,
            'date_from' => $dateFrom,
            'commissions' => $commissionStats,
            'vendors' => [
                'total' => $totalVendors,
                'active' => $activeVendors,
                'pending' => $pendingVendors,
            ],
            'pending_withdrawals' => $pendingWithdrawals,
            'active_subscriptions' => $activeSubscriptions,
        ];
    }

    /**
     * أفضل البائعين (للمشرف)
     */
    public function getTopVendors(int $limit = 10, string $orderBy = 'earnings'): array
    {
        $limit = max(1, min(50, $limit));
        $allowedOrderBy = ['earnings', 'orders', 'products'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'earnings';
        }

        $orderField = 'total_earnings';
        if ($orderBy === 'orders') $orderField = 'total_orders';
        if ($orderBy === 'products') $orderField = 'total_products';

        $sql = "SELECT id, store_name, balance, total_sales, total_earnings, total_orders, total_products 
                FROM {$this->db->prefix}vmp_vendors 
                WHERE status = 'approved' 
                ORDER BY {$orderField} DESC 
                LIMIT %d";

        return $this->db->get_results($this->db->prepare($sql, $limit));
    }

    /* ═══════════════════════════════════════════════════════════ */
    /* دوال مساعدة للتاريخ                                         */
    /* ═══════════════════════════════════════════════════════════ */

    /**
     * GetPeriodStart functionality helper.
     *
     * @param string $period Description index.
     * @return string Output payload.
     */
    private function getPeriodStart(string $period): string
    {
        $now = current_time('timestamp');
        switch ($period) {
            case 'today':
                return date('Y-m-d 00:00:00', $now);
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days', $now));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days', $now));
            case 'year':
                return date('Y-m-d 00:00:00', strtotime('-1 year', $now));
            case 'all':
            default:
                return '2020-01-01 00:00:00';
        }
    }

    /**
     * FormatMonthLabel functionality helper.
     *
     * @param string $ym Description index.
     * @return string Output payload.
     */
    private function formatMonthLabel(string $ym): string
    {
        $parts = explode('-', $ym);
        if (count($parts) !== 2) {
            return $ym;
        }

        $months = [
            '01' => __('يناير', 'vmp'),
            '02' => __('فبراير', 'vmp'),
            '03' => __('مارس', 'vmp'),
            '04' => __('أبريل', 'vmp'),
            '05' => __('مايو', 'vmp'),
            '06' => __('يونيو', 'vmp'),
            '07' => __('يوليو', 'vmp'),
            '08' => __('أغسطس', 'vmp'),
            '09' => __('سبتمبر', 'vmp'),
            '10' => __('أكتوبر', 'vmp'),
            '11' => __('نوفمبر', 'vmp'),
            '12' => __('ديسمبر', 'vmp'),
        ];

        $m = $parts[1];
        $y = $parts[0];
        return ($months[$m] ?? $m) . ' ' . $y;
    }
}
