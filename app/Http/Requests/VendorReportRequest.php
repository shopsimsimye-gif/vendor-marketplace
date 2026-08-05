<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class VendorReportRequest
 *
 * [QA 2026-08-05] Phase B — طلب تقرير البائع (period)
 *
 * @package vendor-marketplace
 */
class VendorReportRequest extends AbstractRequest
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
            'period' => ['string', 'in:today,week,month,quarter,year'],
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
            'period' => __('الفترة', 'vmp'),
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
        if (empty($data['period'])) {
            $data['period'] = 'month';
        }
        return $data;
    }
}
