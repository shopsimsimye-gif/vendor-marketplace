<?php
namespace VMP\Http\Resources;

defined('ABSPATH') || exit;

/**
 * Vendor Resource — explicit allow-list for API responses.
 *
 * PUBLIC fields (returned to everyone):
 *   id, store_name, store_slug, store_description, store_url,
 *   store_logo, store_banner, store_video, rating, is_trusted,
 *   social_links
 *
 * PRIVATE fields (returned only to the vendor themselves or admins):
 *   store_email, store_phone, store_address, store_latitude, store_longitude,
 *   whatsapp_number, balance, total_products, total_orders, total_sales,
 *   subscription_plan, subscription_status, subscription_expiry,
 *   custom_css
 */
class VendorResource
{
    /**
     * Transform vendor object into API-safe array.
     *
     * @param object $vendor Raw vendor object from database.
     * @param bool   $includePrivate Include private fields (vendor's own profile or admin).
     * @return array
     */
    public static function toArray(object $vendor, bool $includePrivate = false): array
    {
        $data = [
            'id'              => (int) ($vendor->id ?? 0),
            'store_name'      => esc_html($vendor->store_name ?? ''),
            'store_slug'      => esc_attr($vendor->store_slug ?? ''),
            'description'     => wp_kses_post($vendor->store_description ?? ''),
            'store_url'       => esc_url($vendor->store_url ?? get_permalink($vendor->id ?? 0)),
            'logo_url'        => esc_url($vendor->store_logo ?? ''),
            'banner_url'      => esc_url($vendor->store_banner ?? ''),
            'video_url'       => esc_url($vendor->store_video ?? ''),
            'rating'          => (float) ($vendor->rating ?? 0.0),
            'is_trusted'      => (bool) ($vendor->is_trusted ?? false),
            'social_links'    => [
                'facebook'  => esc_url($vendor->social_facebook ?? ''),
                'instagram' => esc_url($vendor->social_instagram ?? ''),
                'twitter'   => esc_url($vendor->social_twitter ?? ''),
                'youtube'   => esc_url($vendor->social_youtube ?? ''),
            ],
        ];

        if ($includePrivate) {
            $data['contact'] = [
                'email'     => sanitize_email($vendor->store_email ?? ''),
                'phone'     => sanitize_text_field($vendor->store_phone ?? ''),
                'address'   => sanitize_textarea_field($vendor->store_address ?? ''),
                'latitude'  => (float) ($vendor->store_latitude ?? 0.0),
                'longitude' => (float) ($vendor->store_longitude ?? 0.0),
                'whatsapp'  => sanitize_text_field($vendor->whatsapp_number ?? ''),
            ];

            $data['financial'] = [
                'balance'       => (float) ($vendor->balance ?? 0.0),
                'total_sales'   => (float) ($vendor->total_sales ?? 0.0),
                'total_orders'  => (int) ($vendor->total_orders ?? 0),
                'total_products'=> (int) ($vendor->total_products ?? 0),
            ];

            $data['subscription'] = [
                'plan'      => esc_attr($vendor->subscription_plan ?? ''),
                'status'    => esc_attr($vendor->subscription_status ?? ''),
                'expiry'    => esc_attr($vendor->subscription_expiry ?? ''),
            ];

            $data['custom_css'] = sanitize_textarea_field($vendor->custom_css ?? '');
        }

        return $data;
    }

    /**
     * Transform a collection of vendors.
     *
     * @param array $vendors Array of vendor objects.
     * @return array
     */
    public static function collection(array $vendors): array
    {
        return array_map(fn($v) => self::toArray($v, false), $vendors);
    }
}
