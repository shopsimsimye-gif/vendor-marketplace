<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class DeleteProductRequest
 *
 * @package vendor-marketplace
 */
class DeleteProductRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * ✅ يتحقق من الصلاحية ومن ملكية المنتج
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        if (!current_user_can('vmp_vendor_products')) {
            return false;
        }

        $vendorId  = (int) $this->input('vendor_id', 0);
        $productId = (int) $this->input('product_id', 0);

        // إذا لم يُرسل vendor_id، نستخرجه من المستخدم الحالي
        if ($vendorId <= 0) {
            $userId = get_current_user_id();
            $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);
        }

        if ($vendorId <= 0 || $productId <= 0) {
            return false;
        }

        // التحقق من أن المنتج ينتمي لهذا البائع
        $productVendorId = (int) get_post_meta($productId, '_vmp_vendor_id', true);

        return $productVendorId === $vendorId;
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_id'  => ['integer', 'min:1'],
            'product_id' => ['required', 'integer', 'min:1'],
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
            $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

            if ($vendorId > 0) {
                $data['vendor_id'] = $vendorId;
            }
        }

        return $data;
    }
}
