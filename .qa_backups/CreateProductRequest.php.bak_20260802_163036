<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\DTO\ProductDTO;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Container;

class CreateProductRequest extends AbstractRequest
{
    /**
     * ✅ التحقق من الصلاحية + إصلاح تلقائي
     */
    public function authorize(): bool
    {
        if (current_user_can('vmp_vendor_products')) {
            return true;
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return false;
        }

        try {
            $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
            $vendor = $vendorRepo->findByUserId($userId);
            
            if ($vendor && $vendor->status === 'approved') {
                $user = new \WP_User($userId);
                $user->add_role('vmp_vendor');
                $user->add_cap('vmp_vendor_products');
                
                return current_user_can('vmp_vendor_products');
            }
        } catch (\Exception $e) {
            // تجاهل
        }

        return false;
    }

    /**
     * ✅ تحويل البيانات من النموذج إلى DTO
     */
    public function toDTO(): ProductDTO
    {
        $data = $this->validated();

        // 1. تعيين vendor_id
        if (empty($data['vendor_id'])) {
            $userId = get_current_user_id();
            try {
                $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
                $vendor = $vendorRepo->findByUserId($userId);
                if ($vendor) {
                    $data['vendor_id'] = (int) $vendor->id;
                }
            } catch (\Exception $e) {
                $data['vendor_id'] = (int) get_user_meta($userId, 'vmp_vendor_id', true);
            }
        }

        // 2. تحويل product_name → title
        if (isset($data['product_name'])) {
            $data['title'] = $data['product_name'];
        }

        // 3. تحويل category → category_ids
        if (isset($data['category']) && !empty($data['category'])) {
            $data['category_ids'] = [(int) $data['category']];
        }

        // 4. تحويل manage_stock → stock_status
        if (isset($data['manage_stock'])) {
            if ($data['manage_stock'] === 'yes' && isset($data['stock_quantity'])) {
                $data['stock_status'] = 'instock';
            } elseif ($data['manage_stock'] === 'no') {
                $data['stock_status'] = 'instock';
                $data['stock_quantity'] = 0;
            }
        }

        // 5. التأكد من وجود product_id (0 للإضافة الجديدة)
        if (!isset($data['product_id'])) {
            $data['product_id'] = 0;
        }

        return ProductDTO::fromArray($data);
    }

    /**
     * ✅ قواعد التحقق متوافقة مع النموذج
     */
    protected function rules(): array
    {
        return [
            'product_name'      => ['required', 'string', 'min:3', 'max:255'],
            'regular_price'     => ['required', 'numeric', 'min_value:0'],
            'sale_price'        => ['numeric', 'min_value:0'],
            'category'          => ['integer'],
            'short_description' => ['string'],
            'description'       => ['string'],
            'manage_stock'      => ['string', 'in:yes,no'],
            'stock_quantity'    => ['integer', 'min_value:0'],
            'image_id'          => ['integer'],
        ];
    }

    protected function attributes(): array
    {
        return [
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

    protected function messages(): array
    {
        return [
            'product_name.required'  => __('اسم المنتج مطلوب.', 'vmp'),
            'product_name.min'       => __('اسم المنتج يجب أن يكون 3 أحرف على الأقل.', 'vmp'),
            'regular_price.required' => __('السعر الأساسي مطلوب.', 'vmp'),
        ];
    }
}