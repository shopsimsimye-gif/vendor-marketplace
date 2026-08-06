<?php
/**
 * VendorAuthHelpers — Trait مشتركة بين ProductApiController و VendorApiController.
 *
 * **Shared helper methods for Vendor and Product REST controllers.**
 *
 * يحتوي فقط دوال عرض/تصريح (presentation/auth) مساعدة:
 *  - requiresVendor()         (التحقق من البائع المعتمد)
 *  - formatProductForApi()    (تنسيق كائن المنتج لاستجابة API)
 *  - getAttachmentUrl()       (حل رابط مرفق)
 *
 * ⚠️ **Do NOT place business logic here.** هذا Trait مخصص للمساعدة فقط —
 *    أي منطق أعمال يجب أن يعيش في Service/Repository، وليس هنا.
 *
 * [QA 2026-08-06] استُخرجت من النسختين المكرّبتين لتجنب DRY:
 *  - requiresVendor()         (كلاهما كان متطابقاً حرفياً)
 *  - formatProductForApi()    (متطابقاً مع فرق تعليق فقط)
 *  - getAttachmentUrl()       (متطابقاً حرفياً)
 *
 * يفترض أن الـ Controller المستخدم يمتلك `VendorRepositoryInterface $vendorRepository`
 * محقونة عبر constructor (كلاهما يوفّرها بنفس الاسم).
 *
 * @package VMP\Http\Controllers\Api\Traits
 * @since 3.0.0
 */
namespace VMP\Http\Controllers\Api\Traits;

defined('ABSPATH') || exit;

trait VendorAuthHelpers
{
    /**
     * التحقق من أن المستخدم بائع معتمد.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function requiresVendor(\WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'unauthorized',
                __('يجب تسجيل الدخول أولاً.', 'vmp'),
                ['status' => 401]
            );
        }

        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());

        if (!$vendor || $vendor->status !== 'approved') {
            return new \WP_Error(
                'forbidden',
                __('يجب أن تكون بائعاً معتمداً.', 'vmp'),
                ['status' => 403]
            );
        }

        // ✅ تخزين البائع في الطلب لتجنب تكرار الـ DB query
        $request->set_param('__vendor', $vendor);

        return true;
    }

    /**
     * تنسيق منتج للـ API.
     *
     * @param object $product
     * @return array
     */
    private function formatProductForApi(object $product): array
    {
        $productId = (int) ($product->product_id ?? $product->id ?? 0);

        // ✅ استخدام wc_get_product بدلاً من get_the_title
        $wcProduct = $productId > 0 ? wc_get_product($productId) : null;
        $title     = $wcProduct ? $wcProduct->get_name() : ($product->title ?? '');

        $price = (float) ($product->price ?? 0);

        return [
            'id'           => $productId,
            'title'        => (string) $title,
            'price'        => $price,
            'price_html'   => function_exists('wc_price') ? wc_price($price) : null,
            'status'       => (string) ($product->status ?? ''),
            'stock_status' => (string) ($product->stock_status ?? 'instock'),
            'image_url'    => $this->getAttachmentUrl(
                (int) ($wcProduct ? $wcProduct->get_image_id() : get_post_thumbnail_id($productId)),
                'woocommerce_thumbnail'
            ),
        ];
    }

    /**
     * الحصول على رابط مرفق.
     *
     * @param int    $attachmentId
     * @param string $size
     * @return string
     */
    private function getAttachmentUrl(int $attachmentId, string $size = 'thumbnail'): string
    {
        if ($attachmentId <= 0) {
            return '';
        }
        $url = wp_get_attachment_image_url($attachmentId, $size);
        return $url ? (string) $url : '';
    }
}
