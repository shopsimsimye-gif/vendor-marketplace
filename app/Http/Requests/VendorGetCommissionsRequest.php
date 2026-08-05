<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class VendorGetCommissionsRequest
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
            'status'    => ['nullable', 'string', 'in:pending,paid,rejected,processing'],
            'date_from' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'date_to'   => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
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
            'status'    => __('الحالة', 'vmp'),
            'date_from' => __('تاريخ البداية', 'vmp'),
            'date_to'   => __('تاريخ النهاية', 'vmp'),
        ];
    }
}
