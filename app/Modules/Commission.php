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
    public function ajax_get_commissions(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_commissions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $status = sanitize_text_field($_POST['status'] ?? '');
        $limit = (int) ($_POST['limit'] ?? 50);
        $offset = (int) ($_POST['offset'] ?? 0);

        $commissions = $this->repository->getAllPending($limit);

        wp_send_json_success(['commissions' => $commissions]);
    }

    /**
     * Ajax Pay Commission functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_pay_commission(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_commissions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $commission_id = (int) ($_POST['commission_id'] ?? 0);
        $commission = $this->repository->find($commission_id);

        if (!$commission) {
            wp_send_json_error(['message' => __('العمولة غير موجودة', 'vmp')]);
        }
        if ($commission->status === 'paid') {
            wp_send_json_error(['message' => __('تم دفع هذه العمولة مسبقاً', 'vmp')]);
        }

        if ($this->repository->markAsPaid($commission_id)) {
            $this->container->get('logger')->info('تم دفع عمولة', [
                'commission_id' => $commission_id,
                'vendor_id' => $commission->vendor_id,
                'amount' => $commission->commission_amount,
            ]);
            $this->container->get('event_manager')->trigger(
                'vmp_commission_paid',
                $commission_id,
                (int) $commission->vendor_id,
                (float) $commission->commission_amount
            );
            wp_send_json_success(['message' => __('تم تسجيل الدفع', 'vmp')]);
        }

        wp_send_json_error(['message' => __('حدث خطأ', 'vmp')]);
    }

    /**
     * Ajax Bulk Pay functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_bulk_pay(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_commissions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $ids = array_map('intval', $_POST['ids'] ?? []);
        if (empty($ids)) {
            wp_send_json_error(['message' => __('لم يتم تحديد أي عمولات', 'vmp')]);
        }

        $count = $this->repository->markBulkAsPaid($ids);
        $this->container->get('logger')->info("تم دفع {$count} عمولة دفعياً", ['ids' => $ids]);

        wp_send_json_success([
            'message' => sprintf(__('تم تسجيل دفع %d عمولة', 'vmp'), $count),
            'count' => $count,
        ]);
    }

    /**
     * Ajax Get Stats functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_get_stats(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_commissions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $stats = $this->repository->getAdminStats();
        wp_send_json_success(['stats' => $stats]);
    }

    /**
     * Ajax Vendor Get Commissions functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_vendor_get_commissions(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');
        if (!current_user_can('vmp_vendor_reports')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $status = sanitize_text_field($_POST['status'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        $commissions = $this->repository->getByVendor((int) $vendor->id, [
            'status' => $status,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'limit' => 50,
        ]);

        wp_send_json_success(['commissions' => $commissions]);
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

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $months = (int) ($_POST['months'] ?? 12);
        $data = $this->repository->getMonthlyStats((int) $vendor->id, $months);

        wp_send_json_success(['chart_data' => $data]);
    }
}
