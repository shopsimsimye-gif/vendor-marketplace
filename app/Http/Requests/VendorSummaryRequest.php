<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class VendorSummaryRequest
 *
 * [QA 2026-08-05] Phase B — طلب ملخص البائع (بدون مدخلات)
 *
 * @package vendor-marketplace
 */
class VendorSummaryRequest extends AbstractRequest
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
        return [];
    }
}
