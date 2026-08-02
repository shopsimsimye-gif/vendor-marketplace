<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class DeleteProductRequest
 *
 * Description of administrative platform component DeleteProductRequest.
 *
 * @package vendor-marketplace
 */
class DeleteProductRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_vendor_products');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_id'         => ['integer'],
            'product_id'        => ['required', 'integer'],
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
            'vendor_id'         => __('معرف البائع', 'vmp'),
            'product_id'        => __('معرف المنتج', 'vmp'),
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
        if (empty($data['vendor_id'])) {
            $userId = get_current_user_id();
            $data['vendor_id'] = (int) get_user_meta($userId, 'vmp_vendor_id', true);
        }
        return $data;
    }
}
