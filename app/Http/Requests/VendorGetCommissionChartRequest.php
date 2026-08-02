<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class VendorGetCommissionChartRequest
 *
 * Description of administrative platform component VendorGetCommissionChartRequest.
 *
 * @package vendor-marketplace
 */
class VendorGetCommissionChartRequest extends AbstractRequest
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
            'months' => ['integer'],
        ];
    }

    /**
     * Validated functionality helper.
     *
     * @return array Output payload.
     */
    public function validated(): array
    {
        $data = parent::validated();
        if (empty($data['months'])) {
            $data['months'] = 12;
        }
        return $data;
    }
}
