<?php
namespace VMP\Http\Resources;

defined('ABSPATH') || exit;

/**
 * Product Resource — explicit allow-list for API responses.
 */
class ProductResource
{
    /**
     * Transform product object into API-safe array.
     *
     * @param object $product Raw product object.
     * @return array
     */
    public static function toArray(object $product): array
    {
        return [
            'id'           => (int) ($product->product_id ?? $product->id ?? 0),
            'title'        => esc_html(get_the_title($product->product_id ?? 0) ?: ($product->title ?? '')),
            'slug'         => esc_attr(get_post_field('post_name', $product->product_id ?? 0) ?: ''),
            'price'        => function_exists('wc_price') ? wc_price((float) ($product->price ?? 0)) : (float) ($product->price ?? 0),
            'price_raw'    => (float) ($product->price ?? 0),
            'status'       => esc_attr($product->status ?? ''),
            'stock_status' => esc_attr($product->stock_status ?? 'instock'),
            'image_url'    => esc_url(wp_get_attachment_image_url(get_post_thumbnail_id($product->product_id ?? 0), 'woocommerce_thumbnail') ?: ''),
            'permalink'    => esc_url(get_permalink($product->product_id ?? 0) ?: ''),
        ];
    }

    /**
     * Transform a collection of products.
     */
    public static function collection(array $products): array
    {
        return array_map(fn($p) => self::toArray($p), $products);
    }
}
