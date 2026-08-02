<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminRejectPlanChangeRequest
 *
 * Description of administrative platform component AdminRejectPlanChangeRequest.
 *
 * @package vendor-marketplace
 */
class AdminRejectPlanChangeRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_subscriptions');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'request_id' => ['required', 'integer'],
            'reason'     => ['string'],
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
            'request_id' => __('معرف الطلب', 'vmp'),
        ];
    }
}
