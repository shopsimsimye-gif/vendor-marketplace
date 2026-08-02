<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

// Withdrawal Requests

/**
 * Class RequestWithdrawalRequest
 *
 * Description of administrative platform component RequestWithdrawalRequest.
 *
 * @package vendor-marketplace
 */
class RequestWithdrawalRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_vendor_withdrawals');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'amount'         => ['required', 'numeric'],
            'method'         => ['string'],
            // [QA 2026-08-02] method_details: array اختياري — يُعقَّم داخل WithdrawalService
            'method_details' => ['array'],
        ];
    }

    /**
     * Attributes functionality helper.
     *
     * @return array Output payload.
     */
    protected function attributes(): array
    {
        return [
            'amount' => __('مبلغ السحب', 'vmp'),
            'method' => __('طريقة السحب', 'vmp'),
        ];
    }
}
