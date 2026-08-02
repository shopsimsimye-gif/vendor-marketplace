<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\WithdrawalRepository;
use VMP\Repositories\VendorRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Withdrawal
 *
 * Description of administrative platform component Withdrawal.
 *
 * @package vendor-marketplace
 */
class Withdrawal extends AbstractModule
{
    private WithdrawalRepository $repository;
    private VendorRepository $vendorRepository;

    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->repository = $this->make(WithdrawalRepository::class);
        $this->vendorRepository = $this->make(VendorRepository::class);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        // تم نقل مسارات AJAX إلى ActionDispatcher / RouteRegistry
        // add_action('wp_ajax_vmp_request_withdrawal', [$this, 'ajax_request']);
        // add_action('wp_ajax_vmp_admin_get_withdrawals', [$this, 'ajax_admin_get']);
        // add_action('wp_ajax_vmp_admin_process_withdrawal', [$this, 'ajax_admin_process']);
    }

    /**
     * Ajax Request functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_request(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');
        if (!current_user_can('vmp_vendor_withdrawals')) {
            wp_send_json_error(['message' => __('Unauthorized', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $amount = (float) ($_POST['amount'] ?? 0);
        $min_withdrawal = (float) get_option('vmp_min_withdrawal', 50);
        if ($amount <= 0) {
            wp_send_json_error(['message' => __('المبلغ غير صالح', 'vmp')]);
        }
        if ($amount < $min_withdrawal) {
            wp_send_json_error(['message' => sprintf(__('الحد الأدنى للسحب هو %s', 'vmp'), $min_withdrawal)]);
        }
        if ($amount > $vendor->balance) {
            wp_send_json_error(['message' => __('رصيدك غير كافٍ', 'vmp')]);
        }

        $method = sanitize_text_field($_POST['method'] ?? 'bank_transfer');
        $method_details = $_POST['method_details'] ?? [];

        $withdrawal_id = $this->repository->create([
            'vendor_id' => $vendor->id,
            'amount' => $amount,
            'method' => $method,
            'method_details' => $method_details,
        ]);

        if ($withdrawal_id) {
            $this->vendorRepository->updateBalance($vendor->id, -$amount);
            $this->container->get('event_manager')->trigger('vmp_withdrawal_requested', $withdrawal_id, $vendor->id, $amount);
            wp_send_json_success(['message' => __('تم تقديم طلب السحب بنجاح', 'vmp'), 'withdrawal_id' => $withdrawal_id]);
        }

        wp_send_json_error(['message' => __('حدث خطأ', 'vmp')]);
    }

    /**
     * Ajax Admin Get functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_get(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_withdrawals')) {
            wp_send_json_error(['message' => __('Unauthorized', 'vmp')]);
        }

        $withdrawals = $this->repository->getPending(100);
        wp_send_json_success(['withdrawals' => $withdrawals]);
    }

    /**
     * Ajax Admin Process functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_process(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_withdrawals')) {
            wp_send_json_error(['message' => __('Unauthorized', 'vmp')]);
        }

        $withdrawal_id = (int) ($_POST['withdrawal_id'] ?? 0);
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        $withdrawal = $this->repository->find($withdrawal_id);
        if (!$withdrawal) {
            wp_send_json_error(['message' => __('الطلب غير موجود', 'vmp')]);
        }

        $processed_by = get_current_user_id();
        if ($action_type === 'approve') {
            if ($this->repository->approve($withdrawal_id, $processed_by)) {
                $this->container->get('event_manager')->trigger('vmp_withdrawal_approved', $withdrawal_id, $withdrawal->vendor_id, $withdrawal->amount);
                wp_send_json_success(['message' => __('تم الموافقة على السحب', 'vmp')]);
            }
        } elseif ($action_type === 'reject') {
            $this->vendorRepository->updateBalance($withdrawal->vendor_id, $withdrawal->amount);
            if ($this->repository->reject($withdrawal_id, $processed_by, $reason)) {
                $this->container->get('event_manager')->trigger('vmp_withdrawal_rejected', $withdrawal_id, $withdrawal->vendor_id, $reason);
                wp_send_json_success(['message' => __('تم رفض السحب وإرجاع المبلغ', 'vmp')]);
            }
        }

        wp_send_json_error(['message' => __('حدث خطأ', 'vmp')]);
    }
}
