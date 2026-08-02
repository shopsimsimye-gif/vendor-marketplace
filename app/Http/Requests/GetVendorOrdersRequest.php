<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class GetVendorOrdersRequest
 *
 * Description of administrative platform component GetVendorOrdersRequest.
 *
 * @package vendor-marketplace
 */
class GetVendorOrdersRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_vendor_orders');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'status'    => ['string'],
            'limit'     => ['integer'],
            'offset'    => ['integer'],
        ];
    }
}
