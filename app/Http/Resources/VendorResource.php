<?php
/**
 * VendorResource — تحويل بيانات البائع لـ API (raw data بدون escaping)
 *
 * @package VMP\Http\Resources
 * @since 3.0.0
 */

namespace VMP\Http\Resources;

defined('ABSPATH') || exit;

class VendorResource
{
    /**
     * تحويل كائن البائع إلى array للـ API
     *
     * @param object $vendor         كائن البائع من DB
     * @param bool   $includePrivate تضمين الحقول الخاصة
     * @return array
     */
    public static function toArray(object $vendor, bool $includePrivate = false): array
    {
        $vendorId = (int) ($vendor->id ?? 0);

        $data = [
            'id'           => $vendorId,
            'store_name'   => (string) ($vendor->store_name ?? ''),
            'store_slug'   => (string) ($vendor->store_slug ?? ''),
            'description'  => (string) ($vendor->store_description ?? ''),
            'store_url'    => self::getStoreUrl($vendor),
            'logo_url'     => self::getAttachmentUrl((int) ($vendor->store_logo ?? 0), 'medium'),
            'banner_url'   => self::getAttachmentUrl((int) ($vendor->store_banner ?? 0), 'large'),
            'video_url'    => (string) ($vendor->store_video ?? ''),
            'rating'       => (float) ($vendor->rating ?? 0.0),
            'is_trusted'   => (bool) ($vendor->is_trusted ?? false),
            'social_links' => self::formatSocialLinks($vendor),
        ];

        if ($includePrivate) {
            $data['contact'] = [
                'email'     => (string) ($vendor->store_email ?? ''),
                'phone'     => (string) ($vendor->store_phone ?? ''),
                'address'   => (string) ($vendor->store_address ?? ''),
                'latitude'  => (float) ($vendor->store_latitude ?? 0.0),
                'longitude' => (float) ($vendor->store_longitude ?? 0.0),
                'whatsapp'  => (string) ($vendor->whatsapp_number ?? ''),
            ];

            $data['financial'] = [
                'balance'        => (float) ($vendor->balance ?? 0.0),
                'total_sales'    => (float) ($vendor->total_sales ?? 0.0),
                'total_orders'   => (int) ($vendor->total_orders ?? 0),
                'total_products' => (int) ($vendor->total_products ?? 0),
            ];

            $data['subscription'] = [
                'plan'   => (string) ($vendor->subscription_plan ?? ''),
                'status' => (string) ($vendor->subscription_status ?? ''),
                'expiry' => self::formatDate($vendor->subscription_expiry ?? null),
            ];

            $data['custom_css'] = (string) ($vendor->custom_css ?? '');
        }

        return $data;
    }

    /**
     * تحويل مجموعة من البائعين
     */
    public static function collection(array $vendors): array
    {
        return array_map(static fn($v) => self::toArray($v, false), $vendors);
    }

    /**
     * رابط المتجر العام
     */
    private static function getStoreUrl(object $vendor): string
    {
        if (!empty($vendor->store_url)) {
            return (string) $vendor->store_url;
        }

        $storeBase = get_option('vmp_store_base', 'store');
        $slug      = !empty($vendor->store_slug)
            ? (string) $vendor->store_slug
            : 'vendor-' . (int) ($vendor->id ?? 0);

        return home_url('/' . trailingslashit($storeBase) . $slug . '/');
    }

    /**
     * رابط مرفق (صورة/فيديو)
     */
    private static function getAttachmentUrl(int $attachmentId, string $size = 'medium'): string
    {
        if ($attachmentId <= 0) {
            return '';
        }
        $url = wp_get_attachment_image_url($attachmentId, $size);
        return $url ? (string) $url : '';
    }

    /**
     * تنسيق روابط التواصل الاجتماعي
     */
    private static function formatSocialLinks(object $vendor): ?array
    {
        $links = [
            'facebook'  => (string) ($vendor->social_facebook ?? ''),
            'instagram' => (string) ($vendor->social_instagram ?? ''),
            'twitter'   => (string) ($vendor->social_twitter ?? ''),
            'youtube'   => (string) ($vendor->social_youtube ?? ''),
        ];

        // إزالة الفارغة — إذا لم يوجد أي رابط، أرجع null
        $links = array_filter($links);
        return empty($links) ? null : $links;
    }

    /**
     * تنسيق التاريخ لـ ISO 8601
     */
    private static function formatDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('c', $timestamp) : null;
    }
}
