<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\CommissionRepository;
use VMP\Repositories\VendorRepository;
use VMP\Repositories\SubscriptionPlanRepository;
use VMP\Repositories\SubscriptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وحدة العمولات — تحسب وتدير عمولات كل طلب مكتمل
 */
class Commission extends AbstractModule
{
    private CommissionRepository $repository;
    private VendorRepository $vendorRepository;
    private SubscriptionPlanRepository $planRepository;
    private SubscriptionRepository $subscriptionRepository;

    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->repository = $this->make(CommissionRepository::class);
        $this->vendorRepository = $this->make(VendorRepository::class);
        $this->planRepository = $this->make(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = $this->make(SubscriptionRepository::class);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        // تم نقل مسارات الأجاكس إلى ActionDispatcher / RouteRegistry
        // add_action('wp_ajax_vmp_get_commissions', [$this, 'ajax_get_commissions']);
        // add_action('wp_ajax_vmp_pay_commission', [$this, 'ajax_pay_commission']);
        // add_action('wp_ajax_vmp_bulk_pay_commissions', [$this, 'ajax_bulk_pay']);
        // add_action('wp_ajax_vmp_get_commission_stats', [$this, 'ajax_get_stats']);
        // add_action('wp_ajax_vmp_vendor_get_commissions', [$this, 'ajax_vendor_get_commissions']);
        // add_action('wp_ajax_vmp_vendor_commission_chart', [$this, 'ajax_vendor_chart']);
    }

    /**
     * Calculate Rate functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return float Output payload.
     */
    public function calculate_rate(int $vendor_id): float
    {
        $active_subscription = $this->subscriptionRepository->findActiveByVendor($vendor_id);
        if ($active_subscription) {
            $plan = $this->planRepository->find((int) $active_subscription->plan_id);
            if ($plan) {
                return (float) $plan->commission_rate;
            }
        }

        $free_plan = $this->planRepository->findBySlug('free');
        if ($free_plan) {
            return (float) $free_plan->commission_rate;
        }

        return (float) get_option('vmp_default_commission', 10);
    }

    /**
     * Calculate Amount functionality helper.
     *
     * @param float $total Description index.
     * @param float $rate Description index.
     * @return array Output payload.
     */
    public function calculate_amount(float $total, float $rate): array
    {
        $commission_amount = round(($total * $rate) / 100, 2);
        $vendor_amount = round($total - $commission_amount, 2);
        return [
            'rate' => $rate,
            'commission_amount' => $commission_amount,
            'vendor_amount' => $vendor_amount,
        ];
    }

    /**
     * Ajax Get Commissions functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Pay Commission functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Bulk Pay functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Get Stats functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Vendor Get Commissions functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Vendor Chart functionality helper.
     *
     * @return void Output payload.
     */
}
