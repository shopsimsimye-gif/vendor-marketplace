<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class TrackWhatsappClickRequest
 *
 * @package vendor-marketplace
 */
class TrackWhatsappClickRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return true; // متاح للجميع (مسجلين وغير مسجلين)
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_id'  => ['required', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'page_url'   => ['nullable', 'string', 'url', 'max:2048'],
            'click_type' => ['nullable', 'string', 'in:product,store,profile,banner', 'max:50'],
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
            'vendor_id'  => __('معرف البائع', 'vmp'),
            'product_id' => __('معرف المنتج', 'vmp'),
            'page_url'   => __('رابط الصفحة', 'vmp'),
            'click_type' => __('نوع النقر', 'vmp'),
        ];
    }
}
