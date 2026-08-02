<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminGetVendorOrdersRequest
 *
 * Description of administrative platform component AdminGetVendorOrdersRequest.
 *
 * @package vendor-marketplace
 */
class AdminGetVendorOrdersRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_orders');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_id' => ['integer'],
            'status'    => ['string'],
            'limit'     => ['integer'],
            'offset'    => ['integer'],
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
            'status'    => __('حالة الطلب', 'vmp'),
        ];
    }
}
