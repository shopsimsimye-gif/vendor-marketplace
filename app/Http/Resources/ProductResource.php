<?php
/**
 * ProductResource — تحويل بيانات المنتج لـ API (raw data)
 *
 * @package VMP\Http\Resources
 * @since 3.0.0
 */

namespace VMP\Http\Resources;

defined('ABSPATH') || exit;

class ProductResource
{
    /**
     * تحويل كائن المنتج إلى array للـ API
     *
     * @param object $product كائن المنتج من DB
     * @return array
     */
    public static function toArray(object $product): array
    {
        $productId = (int) ($product->product_id ?? $product->id ?? 0);

        // ✅ استخدام wc_get_product بدلاً من get_the_title (أكثر أماناً في REST)
        $wcProduct = $productId > 0 ? wc_get_product($productId) : null;
        $title     = $wcProduct ? $wcProduct->get_name() : ($product->title ?? '');
        $slug      = $wcProduct ? $wcProduct->get_slug() : ($product->slug ?? '');

        $price = (float) ($product->price ?? ($wcProduct ? $wcProduct->get_price() : 0));

        return [
            'id'           => $productId,
            'title'        => (string) $title,
            'slug'         => (string) $slug,
            'price'        => $price,
            'price_html'   => function_exists('wc_price') ? wc_price($price) : null,
            'status'       => (string) ($product->status ?? ($wcProduct ? $wcProduct->get_status() : 'draft')),
            'stock_status' => (string) ($product->stock_status ?? ($wcProduct ? $wcProduct->get_stock_status() : 'instock')),
            'image_url'    => self::getImageUrl($productId, $wcProduct),
            'permalink'    => $productId > 0 ? (string) get_permalink($productId) : '',
        ];
    }

    /**
     * تحويل مجموعة من المنتجات
     */
    public static function collection(array $products): array
    {
        return array_map(static fn($p) => self::toArray($p), $products);
    }

    /**
     * رابط صورة المنتج
     */
    private static function getImageUrl(int $productId, $wcProduct = null): string
    {
        if (!$productId) {
            return '';
        }

        $imageId = $wcProduct ? $wcProduct->get_image_id() : get_post_thumbnail_id($productId);
        if (!$imageId) {
            return '';
        }

        $url = wp_get_attachment_image_url((int) $imageId, 'woocommerce_thumbnail');
        return $url ? (string) $url : '';
    }
}
