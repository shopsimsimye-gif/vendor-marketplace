<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\CommissionRepository;
use VMP\Repositories\OrderRepository;
use VMP\Repositories\VendorRepository;
use VMP\Repositories\WithdrawalRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Report
 *
 * Description of administrative platform component Report.
 *
 * @package vendor-marketplace
 */
class Report extends AbstractModule
{
    private CommissionRepository $commissionRepository;
    private OrderRepository $orderRepository;
    private VendorRepository $vendorRepository;
    private WithdrawalRepository $withdrawalRepository;

    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->commissionRepository = $this->make(CommissionRepository::class);
        $this->orderRepository = $this->make(OrderRepository::class);
        $this->vendorRepository = $this->make(VendorRepository::class);
        $this->withdrawalRepository = $this->make(WithdrawalRepository::class);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        add_action('wp_ajax_vmp_vendor_report', [$this, 'ajax_vendor_report']);
        add_action('wp_ajax_vmp_vendor_chart', [$this, 'ajax_vendor_chart']);
        add_action('wp_ajax_vmp_vendor_summary', [$this, 'ajax_vendor_summary']);
        add_action('wp_ajax_vmp_admin_report', [$this, 'ajax_admin_report']);
        add_action('wp_ajax_vmp_admin_chart', [$this, 'ajax_admin_chart']);
        add_action('wp_ajax_vmp_admin_top_vendors', [$this, 'ajax_admin_top_vendors']);
    }

    /**
     * Ajax Vendor Summary functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_vendor_summary(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');
        if (!current_user_can('vmp_vendor_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $vid = (int) $vendor->id;
        wp_send_json_success([
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
     * Ajax Vendor Report functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_vendor_report(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');
        if (!current_user_can('vmp_vendor_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $period = sanitize_text_field($_POST['period'] ?? 'month');
        $date_from = $this->get_period_start($period);
        $date_to = current_time('mysql');
        $vid = (int) $vendor->id;

        $commission_stats = $this->commissionRepository->getTotalByVendorAndPeriod($vid, $date_from, $date_to);

        wp_send_json_success([
            'period' => $period,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'stats' => $commission_stats,
            'balance' => (float) $vendor->balance,
        ]);
    }

    /**
     * Ajax Vendor Chart functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_vendor_chart(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');
        if (!current_user_can('vmp_vendor_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $months = (int) ($_POST['months'] ?? 6);
        $months = max(1, min(24, $months));
        $monthly = $this->commissionRepository->getMonthlyStats((int) $vendor->id, $months);

        $labels = [];
        $earnings = [];
        $orders = [];

        foreach ($monthly as $row) {
            $labels[] = $this->format_month_label($row->month);
            $earnings[] = (float) $row->earnings;
            $orders[] = (int) $row->orders;
        }

        wp_send_json_success([
            'labels' => $labels,
            'earnings' => $earnings,
            'orders' => $orders,
        ]);
    }

    /**
     * Ajax Admin Report functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_report(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        global $wpdb;
        $period = sanitize_text_field($_POST['period'] ?? 'month');
        $date_from = $this->get_period_start($period);

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

        wp_send_json_success([
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
     * Ajax Admin Chart functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_chart(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        global $wpdb;
        $months = (int) ($_POST['months'] ?? 6);
        $months = max(1, min(24, $months));

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
            $labels[] = $this->format_month_label($row->month);
            $commissions[] = (float) $row->commissions;
            $sales[] = (float) $row->total_sales;
            $orders[] = (int) $row->orders;
        }

        wp_send_json_success([
            'labels' => $labels,
            'commissions' => $commissions,
            'sales' => $sales,
            'orders' => $orders,
        ]);
    }

    /**
     * Ajax Admin Top Vendors functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_top_vendors(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        global $wpdb;
        $limit = (int) ($_POST['limit'] ?? 10);

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

        wp_send_json_success(['vendors' => $top_vendors]);
    }

    /**
     * Get Period Start functionality helper.
     *
     * @param string $period Description index.
     * @return string Output payload.
     */
    private function get_period_start(string $period): string
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
    private function format_month_label(string $year_month): string
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
