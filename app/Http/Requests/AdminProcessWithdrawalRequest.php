<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminProcessWithdrawalRequest
 *
 * Description of administrative platform component AdminProcessWithdrawalRequest.
 *
 * @package vendor-marketplace
 */
class AdminProcessWithdrawalRequest extends AbstractRequest
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
            'withdrawal_id' => ['required', 'integer'],
            'action_type'   => ['required', 'string'],
            'reason'        => ['string'],
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
            'withdrawal_id' => __('معرف طلب السحب', 'vmp'),
            'action_type'   => __('نوع الإجراء', 'vmp'),
        ];
    }
}
