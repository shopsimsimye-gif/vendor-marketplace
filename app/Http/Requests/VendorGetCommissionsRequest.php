<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class VendorGetCommissionsRequest
 *
 * Description of administrative platform component VendorGetCommissionsRequest.
 *
 * @package vendor-marketplace
 */
class VendorGetCommissionsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_vendor_reports');
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
            'date_from' => ['string'],
            'date_to'   => ['string'],
        ];
    }
}
