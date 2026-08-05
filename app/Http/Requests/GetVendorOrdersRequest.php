<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class GetVendorOrdersRequest
 *
 * ✅ حد أقصى للـ limit لمنع استهلاك الموارد
 * ✅ قيم افتراضية آمنة
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
            'status' => ['string', 'in:pending,processing,completed,cancelled,refunded,on-hold'],
            'limit'  => ['integer', 'min:1', 'max:100'],
            'offset' => ['integer', 'min:0'],
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
            'status' => __('حالة الطلب', 'vmp'),
            'limit'  => __('الحد الأقصى', 'vmp'),
            'offset' => __('الإزاحة', 'vmp'),
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

        if (empty($data['limit'])) {
            $data['limit'] = 20;
        }
        if (empty($data['offset'])) {
            $data['offset'] = 0;
        }

        return $data;
    }
}
