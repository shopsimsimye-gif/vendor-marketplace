<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class WithdrawalRequest
 *
 * @package vendor-marketplace
 */
class WithdrawalRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_withdrawals');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_id'      => ['required', 'integer', 'min:1'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'method'         => ['required', 'string', 'in:bank_transfer,paypal,other'],
            'method_details' => ['nullable', 'array'],
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
            'vendor_id'      => __('معرف البائع', 'vmp'),
            'amount'         => __('المبلغ', 'vmp'),
            'method'         => __('طريقة السحب', 'vmp'),
            'method_details' => __('تفاصيل السحب', 'vmp'),
        ];
    }

    /**
     * Validate functionality helper.
     *
     * @return bool Output payload.
     */
    public function validate(): bool
    {
        if (!parent::validate()) {
            return false;
        }

        $minWithdrawal = (float) get_option('vmp_min_withdrawal', 50);
        $amount = (float) $this->input('amount', 0);

        if ($amount < $minWithdrawal) {
            $this->addError(sprintf(__('الحد الأدنى للسحب هو %s.', 'vmp'), $minWithdrawal));
            return false;
        }

        return true;
    }
}
