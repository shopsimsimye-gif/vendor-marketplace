<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\DTO\ProductDTO;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Container;

class UpdateProductRequest extends AbstractRequest
{
    /**
     * التحقق من الصلاحيات
     * ✅ إصلاح صلاحية vmp_vendor_products
     */
    public function authorize(): bool
    {
        if (current_user_can('vmp_vendor_products')) {
            return true;
        }

        // محاولة إصلاح الصلاحية تلقائياً
        $userId = get_current_user_id();
        if (!$userId) {
            return false;
        }

        try {
            $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
            $vendor = $vendorRepo->findByUserId($userId);
            
            if ($vendor && $vendor->status === 'approved') {
                $user = new \WP_User($userId);
                if (!in_array('vmp_vendor', (array) $user->roles)) {
                    $user->add_role('vmp_vendor');
                }
                $user->add_cap('vmp_vendor_products');
                
                if (current_user_can('vmp_vendor_products')) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // تجاهل
        }

        return false;
    }

    /**
     * تحويل بيانات الطلب إلى DTO
     * ✅ تعيين الحقول بشكل صحيح من النموذج
     */
    public function toDTO(): ProductDTO
    {
        $data = $this->validated();

        // ✅ تعيين vendor_id من المستخدم الحالي
        if (empty($data['vendor_id'])) {
            try {
                $userId = get_current_user_id();
                $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
                $vendor = $vendorRepo->findByUserId($userId);
                if ($vendor) {
                    $data['vendor_id'] = (int) $vendor->id;
                } else {
                    // Fallback: user_meta
                    $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);
                    if ($vendorId) {
                        $data['vendor_id'] = $vendorId;
                    }
                }
            } catch (\Exception $e) {
                $data['vendor_id'] = (int) get_user_meta(get_current_user_id(), 'vmp_vendor_id', true);
            }
        }

        // ✅ تحويل الحقول من النموذج إلى ProductDTO
        // النموذج يرسل product_name وليس title
        if (isset($data['product_name'])) {
            $data['title'] = $data['product_name'];
        }

        // النموذج يرسل category (ID واحد) وليس category_ids (مصفوفة)
        if (isset($data['category']) && !empty($data['category'])) {
            $data['category_ids'] = [(int) $data['category']];
        }

        // النموذج يرسل manage_stock (yes/no) وليس stock_status
        if (isset($data['manage_stock'])) {
            if ($data['manage_stock'] === 'yes' && isset($data['stock_quantity'])) {
                $data['stock_status'] = 'instock'; // سيتم إدارة المخزون
            } elseif ($data['manage_stock'] === 'no') {
                $data['stock_status'] = 'instock';
                $data['stock_quantity'] = 0;
            }
        }

        // ✅ product_id هو vendor_product_id من النموذج
        if (isset($data['vendor_product_id'])) {
            $data['product_id'] = (int) $data['vendor_product_id'];
        }

        return ProductDTO::fromArray($data);
    }

    /**
     * قواعد التحقق
     * ✅ متوافقة مع الحقول المرسلة
     */
    protected function rules(): array
    {
        return [
            'vendor_product_id' => ['required', 'integer'],
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
            'regular_price.min_value'    => __('السعر الأساسي يجب أن يكون أكبر من أو يساوي 0.', 'vmp'),
            'category.integer'           => __('التصنيف يجب أن يكون رقماً صحيحاً.', 'vmp'),
            'manage_stock.in'            => __('قيمة إدارة المخزون غير صالحة.', 'vmp'),
        ];
    }
}