<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminCreatePlanRequest
 *
 * Description of administrative platform component AdminCreatePlanRequest.
 *
 * @package vendor-marketplace
 */
class AdminCreatePlanRequest extends AbstractRequest
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
            'name'             => ['required', 'string'],
            'price'            => ['required', 'numeric'],
            'billing_period'   => ['string'],
            'billing_interval' => ['integer'],
            'max_products'     => ['integer'],
            'commission_rate'  => ['numeric'],
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
            'name'  => __('اسم الخطة', 'vmp'),
            'price' => __('السعر', 'vmp'),
        ];
    }
}
