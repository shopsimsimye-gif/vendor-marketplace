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

        // تعيين vendor_id من سياق المصادقة حصراً (أمان)
        // ⚠️ لا نثق أبداً بـ vendor_id القادم من POST — مصدر الهوية هو
        // get_current_user_id() (المستخدم المصادق عليه)، ونبني vendor_id
        // داخلياً للتوافق مع طبقة الدومين.
        $data = $this->resolveVendorId($data);

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

        // ── Media Library Integration (Phase 3): تحقق ملكية الوسائط ──
        $imageId = (int) $this->get('image_id', 0);
        $galleryIds = $this->get('gallery_image_ids', []);
        $galleryIds = is_array($galleryIds) ? array_map('intval', $galleryIds) : [];

        $vendorId = get_current_user_id();
        try {
            $mediaRepo = Container::getInstance()->make(\VMP\Contracts\MediaRepositoryInterface::class);
        } catch (\Exception $e) {
            $mediaRepo = null;
        }

        // الصورة الرئيسية
        if ($imageId > 0) {
            $media = $mediaRepo ? $mediaRepo->findByAttachment($imageId) : null;
            $wpAttachment = get_post($imageId); // $imageId = attachment_id (معرّف WP)

            $isOwner = ($media && (int) $media->vendorId === $vendorId)
                    || ($wpAttachment && (int) $wpAttachment->post_author === $vendorId);

            if (!$isOwner) {
                $imageId = 0;
            }
        }

        // معرض الصور
        $validGallery = [];
        foreach ($galleryIds as $gid) {
            $gid = (int) $gid;
            if ($gid <= 0) { continue; }
            $media = $mediaRepo ? $mediaRepo->findByAttachment($gid) : null;
            $wpAttachment = get_post($gid); // $gid = attachment_id

            $isOwner = ($media && (int) $media->vendorId === $vendorId)
                    || ($wpAttachment && (int) $wpAttachment->post_author === $vendorId);

            if ($isOwner) {
                $validGallery[] = $gid;
            }
        }

        $data['image_id']          = $imageId;
        $data['gallery_image_ids'] = $validGallery;
        // ── End Media Verification ──

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
            'sale_price'        => ['numeric', 'min:0'],
            'category'          => ['integer', 'min:1'],
            'short_description' => ['string', 'max:500'],
            'description'       => ['string'],
            'manage_stock'      => ['string', 'in:yes,no'],
            'stock_quantity'    => ['integer', 'min:0'],
            'image_id'          => ['integer', 'min:1'],
            'gallery_image_ids' => ['array'],
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
            'gallery_image_ids' => __('معرض الصور', 'vmp'),
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

    /**
     * اشتقاق vendor_id من سياق المصادقة فقط — يتجاهل أي قيمة من POST.
     *
     * @param array $data
     * @return array
     */
    private function resolveVendorId(array $data): array
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            unset($data['vendor_id']);
            return $data;
        }

        try {
            $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
            $vendor = $vendorRepo->findByUserId($userId);
            if ($vendor) {
                $data['vendor_id'] = (int) $vendor->id;
                return $data;
            }
        } catch (\Exception $e) {
            // نتابع إلى fallback
        }

        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);
        if ($vendorId > 0) {
            $data['vendor_id'] = $vendorId;
        } else {
            unset($data['vendor_id']);
        }

        return $data;
    }
}
