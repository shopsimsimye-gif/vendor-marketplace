<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Contracts\CommissionRepositoryInterface;
use VMP\Contracts\OrderRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\WithdrawalRepositoryInterface;
use VMP\Http\Requests\VendorReportRequest;
use VMP\Http\Requests\VendorChartRequest;
use VMP\Http\Requests\VendorSummaryRequest;
use VMP\Http\Requests\AdminReportRequest;
use VMP\Http\Requests\AdminChartRequest;
use VMP\Http\Requests\AdminTopVendorsRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ErrorResponse;
use VMP\Http\Responses\ApiResponse;

/**
 * Class ReportController
 *
 * [QA 2026-08-05] Phase B — نُقلت معالجة AJAX لوحدة Report من VMP\Modules\Report
 * (تسجيل add_action مباشر) إلى هذا الـ Controller عبر RouteRegistry في
 * CoreServiceProvider. المنطق مطابق للنص الأصلي دون تغيير؛ تحل الـ Requests مكان
 * check_ajax_referer + فحص الصلاحيات (authorize).
 *
 * @package vendor-marketplace
 */
class ReportController extends BaseController
{
    public function __construct(
        private CommissionRepositoryInterface $commissionRepository,
        private OrderRepositoryInterface $orderRepository,
        private VendorRepositoryInterface $vendorRepository,
        private WithdrawalRepositoryInterface $withdrawalRepository
    ) {}

    /**
     * Ajax Vendor Summary functionality helper.
     *
     * @param VendorSummaryRequest $request Description index.
     * @return ApiResponse Output payload.
     */
    public function vendorSummary(VendorSummaryRequest $request): ApiResponse
    {
        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            return new ErrorResponse(message: __('البائع غير موجود', 'vmp'), statusCode: 404);
        }

        $vid = (int) $vendor->id;
        return new SuccessResponse(data: [
            'balance' => (float) $vendor->balance,
            'total_sales' => $this->orderRepository->getTotalSales($vid),
            'total_earnings' => $this->orderRepository->getTotalEarnings($vid),
            'total_orders' => $this->orderRepository->countByVendor($vid),
            'pending_orders' => $this->orderRepository->countByVendor($vid, 'pending'),
            'completed_orders' => $this->orderRepository->countByVendor($vid, 'completed'),
            'total_products' => (int) $vendor->total_products,
        ]);
    }

    /**
     * Vendor Report functionality helper.
     *
     * @param VendorReportRequest $request Description index.
     * @return ApiResponse Output payload.
     */
    public function vendorReport(VendorReportRequest $request): ApiResponse
    {
        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            return new ErrorResponse(message: __('البائع غير موجود', 'vmp'), statusCode: 404);
        }

        $period = $request->validated()['period'] ?? 'month';
        $date_from = $this->getPeriodStart($period);
        $date_to = current_time('mysql');
        $vid = (int) $vendor->id;

        $commission_stats = $this->commissionRepository->getTotalByVendorAndPeriod($vid, $date_from, $date_to);

        return new SuccessResponse(data: [
            'period' => $period,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'stats' => $commission_stats,
            'balance' => (float) $vendor->balance,
        ]);
    }

    /**
     * Vendor Chart functionality helper.
     *
     * @param VendorChartRequest $request Description index.
     * @return ApiResponse Output payload.
     */
    public function vendorChart(VendorChartRequest $request): ApiResponse
    {
        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            return new ErrorResponse(message: __('البائع غير موجود', 'vmp'), statusCode: 404);
        }

        $months = $request->validated()['months'] ?? 6;
        $monthly = $this->commissionRepository->getMonthlyStats((int) $vendor->id, $months);

        $labels = [];
        $earnings = [];
        $orders = [];

        foreach ($monthly as $row) {
            $labels[] = $this->formatMonthLabel($row->month);
            $earnings[] = (float) $row->earnings;
            $orders[] = (int) $row->orders;
        }

        return new SuccessResponse(data: [
            'labels' => $labels,
            'earnings' => $earnings,
            'orders' => $orders,
        ]);
    }

    /**
     * Admin Report functionality helper.
     *
     * @param AdminReportRequest $request Description index.
     * @return ApiResponse Output payload.
     */
    public function adminReport(AdminReportRequest $request): ApiResponse
    {
        global $wpdb;
        $period = $request->validated()['period'] ?? 'month';
        $date_from = $this->getPeriodStart($period);

        $commission_stats = $this->commissionRepository->getAdminStats();
        $total_vendors = $this->vendorRepository->getCount();
        $active_vendors = $this->vendorRepository->getCount('approved');
        $pending_vendors = $this->vendorRepository->getCount('pending');

        $pending_withdrawals = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}vmp_withdrawals WHERE status = 'pending'"
        );

        $active_subscriptions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}vmp_vendor_subscriptions WHERE status = 'active'"
        );

        return new SuccessResponse(data: [
            'period' => $period,
            'commission_stats' => $commission_stats,
            'total_vendors' => $total_vendors,
            'active_vendors' => $active_vendors,
            'pending_vendors' => $pending_vendors,
            'pending_withdrawals' => $pending_withdrawals,
            'active_subscriptions' => $active_subscriptions,
        ]);
    }

    /**
     * Admin Chart functionality helper.
     *
     * @param AdminChartRequest $request Description index.
     * @return ApiResponse Output payload.
     */
    public function adminChart(AdminChartRequest $request): ApiResponse
    {
        global $wpdb;
        $months = $request->validated()['months'] ?? 6;

        $monthly = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    DATE_FORMAT(created_at, '%%Y-%%m') AS month,
                    COALESCE(SUM(commission_amount), 0) AS commissions,
                    COALESCE(SUM(vendor_amount), 0)     AS vendor_earnings,
                    COALESCE(SUM(amount), 0)            AS total_sales,
                    COUNT(*)                            AS orders
                 FROM {$wpdb->prefix}vmp_commissions
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
                 ORDER BY month ASC",
                $months
            )
        );

        $labels = [];
        $commissions = [];
        $sales = [];
        $orders = [];

        foreach ($monthly as $row) {
            $labels[] = $this->formatMonthLabel($row->month);
            $commissions[] = (float) $row->commissions;
            $sales[] = (float) $row->total_sales;
            $orders[] = (int) $row->orders;
        }

        return new SuccessResponse(data: [
            'labels' => $labels,
            'commissions' => $commissions,
            'sales' => $sales,
            'orders' => $orders,
        ]);
    }

    /**
     * Admin Top Vendors functionality helper.
     *
     * @param AdminTopVendorsRequest $request Description index.
     * @return ApiResponse Output payload.
     */
    public function adminTopVendors(AdminTopVendorsRequest $request): ApiResponse
    {
        global $wpdb;
        $limit = $request->validated()['limit'] ?? 10;

        $top_vendors = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    v.id, v.store_name, v.store_slug,
                    COALESCE(SUM(c.amount), 0)            AS total_sales,
                    COALESCE(SUM(c.commission_amount), 0) AS total_commissions,
                    COUNT(DISTINCT c.order_id)            AS total_orders
                 FROM {$wpdb->prefix}vmp_vendors v
                 LEFT JOIN {$wpdb->prefix}vmp_commissions c ON v.id = c.vendor_id
                 WHERE v.status = 'approved'
                 GROUP BY v.id
                 ORDER BY total_sales DESC
                 LIMIT %d",
                $limit
            )
        );

        return new SuccessResponse(data: ['vendors' => $top_vendors]);
    }

    /**
     * Get Period Start functionality helper.
     *
     * @param string $period Description index.
     * @return string Output payload.
     */
    private function getPeriodStart(string $period): string
    {
        $date = new \DateTime('now', new \DateTimeZone(wp_timezone_string()));
        switch ($period) {
            case 'today':
                $date->setTime(0, 0, 0);
                break;
            case 'week':
                $date->modify('-7 days');
                break;
            case 'month':
                $date->modify('-30 days');
                break;
            case 'quarter':
                $date->modify('-90 days');
                break;
            case 'year':
                $date->modify('-365 days');
                break;
            default:
                $date->modify('-30 days');
        }
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Format Month Label functionality helper.
     *
     * @param string $year_month Description index.
     * @return string Output payload.
     */
    private function formatMonthLabel(string $year_month): string
    {
        $months_ar = [
            '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس',
            '04' => 'أبريل', '05' => 'مايو', '06' => 'يونيو',
            '07' => 'يوليو', '08' => 'أغسطس', '09' => 'سبتمبر',
            '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر',
        ];
        [$year, $month] = explode('-', $year_month);
        return ($months_ar[$month] ?? $month) . ' ' . $year;
    }
}
