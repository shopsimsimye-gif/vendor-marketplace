<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class SubscribeRequest
 *
 * @package vendor-marketplace
 */
class SubscribeRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_vendor') 
            || current_user_can('vmp_manage_subscriptions');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'min:1'],
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
            'plan_id' => __('معرف الخطة', 'vmp'),
        ];
    }
}
