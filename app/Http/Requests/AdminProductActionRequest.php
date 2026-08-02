<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminProductActionRequest
 *
 * Description of administrative platform component AdminProductActionRequest.
 *
 * @package vendor-marketplace
 */
class AdminProductActionRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_products');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_product_id' => ['required', 'integer'],
            'reason'            => ['string'],
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
            'vendor_product_id' => __('معرف منتج البائع', 'vmp'),
            'reason'            => __('سبب الرفض', 'vmp'),
        ];
    }
}
