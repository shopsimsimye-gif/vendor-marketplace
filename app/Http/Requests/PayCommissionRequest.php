<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class PayCommissionRequest
 *
 * Description of administrative platform component PayCommissionRequest.
 *
 * @package vendor-marketplace
 */
class PayCommissionRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_commissions');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'commission_id' => ['required', 'integer'],
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
            'commission_id' => __('معرف العمولة', 'vmp'),
        ];
    }
}
