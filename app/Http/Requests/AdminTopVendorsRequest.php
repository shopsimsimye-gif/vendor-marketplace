<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminTopVendorsRequest
 *
 * [QA 2026-08-05] Phase B — طلب كبار البائعين (limit)
 *
 * @package vendor-marketplace
 */
class AdminTopVendorsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_reports');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'limit' => ['integer', 'min:1', 'max:100'],
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
            'limit' => __('الحد الأقصى', 'vmp'),
        ];
    }
}
