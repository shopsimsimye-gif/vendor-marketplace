<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class SubscriptionRequest
 *
 * Description of administrative platform component SubscriptionRequest.
 *
 * @package vendor-marketplace
 */
class SubscriptionRequest extends AbstractRequest
{
    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer'],
            'plan_id'   => ['required', 'integer'],
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
