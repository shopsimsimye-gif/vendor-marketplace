<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class GetWhatsappStatsRequest
 *
 * ✅ صلاحية محددة بدلاً من is_user_logged_in فقط
 * ✅ قيم period محددة
 *
 * @package vendor-marketplace
 */
class GetWhatsappStatsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_vendor')
            || current_user_can('vmp_manage_orders');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'period' => ['string', 'in:day,week,month,year'],
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
            'period' => __('الفترة الزمنية', 'vmp'),
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
