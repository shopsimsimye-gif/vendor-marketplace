<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\DTO\ProductDTO;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Container;

/**
 * Class UpdateProductRequest
 *
 * @package vendor-marketplace
 */
class UpdateProductRequest extends AbstractRequest
{
    /**
     * التحقق من الصلاحيات
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_vendor_products');
    }

    /**
     * تحويل بيانات الطلب إلى DTO
     */
    public function toDTO(): ProductDTO
    {
        $data = $this->validated();

        // تعيين vendor_id من المستخدم الحالي
        if (empty($data['vendor_id'])) {
            try {
                $userId = get_current_user_id();
                $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
                $vendor = $vendorRepo->findByUserId($userId);
                if ($vendor) {
                    $data['vendor_id'] = (int) $vendor->id;
                } else {
                    $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);
                    if ($vendorId > 0) {
                        $data['vendor_id'] = $vendorId;
                    }
                }
            } catch (\Exception $e) {
                $vendorId = (int) get_user_meta(get_current_user_id(), 'vmp_vendor_id', true);
                if ($vendorId > 0) {
                    $data['vendor_id'] = $vendorId;
                }
            }
        }

        // تحويل الحقول من النموذج إلى ProductDTO
        if (isset($data['product_name'])) {
            $data['title'] = $data['product_name'];
        }

        if (isset($data['category']) && !empty($data['category'])) {
            $data['category_ids'] = [(int) $data['category']];
        }

        if (isset($data['manage_stock'])) {
            if ($data['manage_stock'] === 'yes' && isset($data['stock_quantity'])) {
                $data['stock_status'] = 'instock';
            } elseif ($data['manage_stock'] === 'no') {
                $data['stock_status'] = 'instock';
                $data['stock_quantity'] = 0;
            }
        }

        if (isset($data['vendor_product_id'])) {
            $data['product_id'] = (int) $data['vendor_product_id'];
        }

        return ProductDTO::fromArray($data);
    }

    /**
     * قواعد التحقق
     */
    protected function rules(): array
    {
        return [
            'vendor_product_id' => ['required', 'integer', 'min:1'],
            'product_name'      => ['required', 'string', 'min:3', 'max:255'],
            'regular_price'     => ['required', 'numeric', 'min:0'],
            'sale_price'        => ['nullable', 'numeric', 'min:0'],
            'category'          => ['nullable', 'integer', 'min:1'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description'       => ['nullable', 'string'],
            'manage_stock'      => ['nullable', 'string', 'in:yes,no'],
            'stock_quantity'    => ['nullable', 'integer', 'min:0'],
            'image_id'          => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * أسماء الحقول للعرض
     */
    protected function attributes(): array
    {
        return [
            'vendor_product_id' => __('معرف المنتج', 'vmp'),
            'product_name'      => __('اسم المنتج', 'vmp'),
            'regular_price'     => __('السعر الأساسي', 'vmp'),
            'sale_price'        => __('سعر التخفيض', 'vmp'),
            'category'          => __('التصنيف', 'vmp'),
            'short_description' => __('الوصف القصير', 'vmp'),
            'description'       => __('الوصف', 'vmp'),
            'manage_stock'      => __('إدارة المخزون', 'vmp'),
            'stock_quantity'    => __('كمية المخزون', 'vmp'),
            'image_id'          => __('الصورة الرئيسية', 'vmp'),
        ];
    }

    /**
     * رسائل مخصصة
     */
    protected function messages(): array
    {
        return [
            'vendor_product_id.required' => __('معرف المنتج مطلوب.', 'vmp'),
            'product_name.required'      => __('اسم المنتج مطلوب.', 'vmp'),
            'product_name.min'           => __('اسم المنتج يجب أن يكون 3 أحرف على الأقل.', 'vmp'),
            'regular_price.required'     => __('السعر الأساسي مطلوب.', 'vmp'),
            'regular_price.min'          => __('السعر الأساسي يجب أن يكون أكبر من أو يساوي 0.', 'vmp'),
            'category.integer'           => __('التصنيف يجب أن يكون رقماً صحيحاً.', 'vmp'),
            'manage_stock.in'            => __('قيمة إدارة المخزون غير صالحة.', 'vmp'),
        ];
    }
}
