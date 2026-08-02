<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminApprovePlanChangeRequest
 *
 * Description of administrative platform component AdminApprovePlanChangeRequest.
 *
 * @package vendor-marketplace
 */
class AdminApprovePlanChangeRequest extends AbstractRequest
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
