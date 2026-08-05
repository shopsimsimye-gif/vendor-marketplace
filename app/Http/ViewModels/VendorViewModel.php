<?php
/**
 * VendorViewModel — يُحضّر بيانات البائع للعرض في القوالب
 *
 * @package VMP\Http\ViewModels
 * @since 3.0.0
 */

namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

use VMP\DTO\VendorDTO;

class VendorViewModel extends AbstractViewModel
{
    public function __construct(
        private VendorDTO $vendor,
        private array     $stats = []
    ) {}

    /**
     * تحويل البيانات إلى array جاهز للعرض
     */
    public function toArray(): array
    {
        $storeBase = get_option('vmp_store_base', 'store');
        $slug      = !empty($this->vendor->storeSlug)
            ? (string) $this->vendor->storeSlug
            : 'vendor-' . (int) ($this->vendor->id ?? 0);

        return [
            'vendor_id'            => (int) ($this->vendor->id ?? 0),
            'store_name'           => $this->e((string) ($this->vendor->storeName ?? '')),
            'store_slug'           => $this->attr($slug),
            'store_description'    => wp_kses_post((string) ($this->vendor->storeDescription ?? '')),
            'store_address'        => $this->e((string) ($this->vendor->storeAddress ?? '')),
            'store_phone'          => $this->e((string) ($this->vendor->storePhone ?? '')),
            'store_email'          => $this->e((string) ($this->vendor->storeEmail ?? '')),
            'whatsapp_number'      => $this->e((string) ($this->vendor->whatsappNumber ?? '')),
            'status'               => (string) ($this->vendor->status ?? 'pending'),
            'status_label'         => $this->getStatusLabel(),
            'status_class'         => $this->getStatusClass(),
            'is_trusted'           => !empty($this->vendor->isTrusted),
            'balance'              => $this->money((float) ($this->vendor->balance ?? 0)),
            'balance_raw'          => (float) ($this->vendor->balance ?? 0),
            'rating'               => $this->formatRating((float) ($this->vendor->rating ?? 0)),
            'review_count'         => (int) ($this->vendor->reviewCount ?? 0),
            'total_products'       => (int) ($this->vendor->totalProducts ?? 0),
            'total_orders'         => (int) ($this->vendor->totalOrders ?? 0),
            'total_sales'          => $this->money((float) ($this->vendor->totalSales ?? 0)),
            'subscription_plan'    => $this->e((string) ($this->vendor->subscriptionPlan ?? '')),
            'subscription_status'  => (string) ($this->vendor->subscriptionStatus ?? 'inactive'),
            'subscription_expiry'  => $this->formatDate($this->vendor->subscriptionExpiry ?? null),
            'store_url'            => $this->url(home_url('/' . trailingslashit($storeBase) . $slug . '/')),
            'dashboard_url'        => $this->url($this->getDashboardUrl()),
            'logo_url'             => $this->getLogoUrl(),
            'banner_url'           => $this->getBannerUrl(),
            'stats'                => $this->stats,
        ];
    }

    /**
     * تسمية حالة البائع
     */
    private function getStatusLabel(): string
    {
        $status = (string) ($this->vendor->status ?? 'pending');

        $labels = [
            'pending'  => __('قيد المراجعة', 'vmp'),
            'approved' => __('مفعّل', 'vmp'),
            'rejected' => __('مرفوض', 'vmp'),
            'banned'   => __('محظور', 'vmp'),
            'inactive' => __('غير نشط', 'vmp'),
        ];

        return $labels[$status] ?? __('غير معروف', 'vmp');
    }

    /**
     * CSS class لحالة البائع
     */
    private function getStatusClass(): string
    {
        $status = (string) ($this->vendor->status ?? 'pending');

        $classes = [
            'pending'  => 'vmp-status--warning',
            'approved' => 'vmp-status--success',
            'rejected' => 'vmp-status--danger',
            'banned'   => 'vmp-status--danger',
            'inactive' => 'vmp-status--secondary',
        ];

        return $classes[$status] ?? '';
    }

    /**
     * رابط لوحة التحكم (ديناميكي)
     */
    private function getDashboardUrl(): string
    {
        $settings = get_option('vmp_settings', []);
        $pageId   = (int) ($settings['display']['dashboard_page'] ?? 0);

        if ($pageId && get_post($pageId)) {
            return get_permalink($pageId);
        }

        return home_url('/vendor-dashboard/');
    }

    /**
     * رابط الشعار مع صورة افتراضية
     */
    private function getLogoUrl(): string
    {
        $logoId = (int) ($this->vendor->storeLogo ?? 0);

        if ($logoId > 0 && wp_attachment_is_image($logoId)) {
            $url = wp_get_attachment_image_url($logoId, 'thumbnail');
            if ($url) {
                return $this->url($url);
            }
        }

        // ✅ صورة افتراضية
        return $this->url(VMP_PLUGIN_URL . 'assets/images/default-logo.png');
    }

    /**
     * رابط الغلاف مع صورة افتراضية
     */
    private function getBannerUrl(): string
    {
        $bannerId = (int) ($this->vendor->storeBanner ?? 0);

        if ($bannerId > 0 && wp_attachment_is_image($bannerId)) {
            $url = wp_get_attachment_image_url($bannerId, 'large');
            if ($url) {
                return $this->url($url);
            }
        }

        // ✅ صورة افتراضية
        return $this->url(VMP_PLUGIN_URL . 'assets/images/default-banner.jpg');
    }

    /**
     * تنسيق التاريخ
     */
    private function formatDate(?string $date): string
    {
        if (empty($date)) {
            return __('غير محدد', 'vmp');
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return (string) $date;
        }

        return wp_date(get_option('date_format'), $timestamp) ?: (string) $date;
    }

    /**
     * تنسيق التقييم (تجنب مشاكل locale)
     */
    private function formatRating(float $rating): string
    {
        return number_format($rating, 1, '.', '');
    }
}
