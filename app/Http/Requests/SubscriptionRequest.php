<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class SubscriptionRequest
 *
 * @package vendor-marketplace
 */
class SubscriptionRequest extends AbstractRequest
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
            'vendor_id' => ['required', 'integer', 'min:1'],
            'plan_id'   => ['required', 'integer', 'min:1'],
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
            'vendor_id' => __('معرف البائع', 'vmp'),
            'plan_id'   => __('معرف الخطة', 'vmp'),
        ];
    }
}
