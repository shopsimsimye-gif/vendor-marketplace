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
        // [QA 2026-08-05] Phase B — تم نقل تسجيل جميع مسارات AJAX إلى RouteRegistry
        // في CoreServiceProvider (عبر ReportController). المسارات الستة:
        //   vmp_vendor_summary, vmp_vendor_report, vmp_vendor_chart,
        //   vmp_admin_report,   vmp_admin_chart,   vmp_admin_top_vendors
        // الأسطر أدناه معطّلة لتجنب الازدواجية (الإبقاء للتوثيق).
        // add_action('wp_ajax_vmp_vendor_report', [$this, 'ajax_vendor_report']);
        // add_action('wp_ajax_vmp_vendor_chart', [$this, 'ajax_vendor_chart']);
        // add_action('wp_ajax_vmp_vendor_summary', [$this, 'ajax_vendor_summary']);
        // add_action('wp_ajax_vmp_admin_report', [$this, 'ajax_admin_report']);
        // add_action('wp_ajax_vmp_admin_chart', [$this, 'ajax_admin_chart']);
        // add_action('wp_ajax_vmp_admin_top_vendors', [$this, 'ajax_admin_top_vendors']);
    }

    /**
     * Ajax Vendor Summary functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Vendor Report functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Vendor Chart functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Admin Report functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Admin Chart functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Admin Top Vendors functionality helper.
     *
     * @return void Output payload.
     */

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
