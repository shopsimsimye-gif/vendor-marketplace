<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminUpdatePlanRequest
 *
 * Description of administrative platform component AdminUpdatePlanRequest.
 *
 * @package vendor-marketplace
 */
class AdminUpdatePlanRequest extends AbstractRequest
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
            'plan_id'          => ['required', 'integer'],
            'name'             => ['string'],
            'price'            => ['numeric'],
            'billing_period'   => ['string'],
            'billing_interval' => ['integer'],
            'max_products'     => ['integer'],
            'commission_rate'  => ['numeric'],
            'is_active'        => ['integer'],
            'sort_order'       => ['integer'],
            'features'         => ['array'],
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
