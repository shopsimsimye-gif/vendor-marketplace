<?php
namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

use VMP\DTO\VendorDTO;

/**
 * VendorViewModel — يُحضّر بيانات البائع للعرض في القوالب
 *
 * يتولى:
 * - تحويل VendorDTO لبيانات جاهزة للعرض (مُنظَّفة ومُنسَّقة)
 * - حساب الحالات والـ labels
 * - بناء URLs المتجر والصور
 */
class VendorViewModel extends AbstractViewModel
{
    public function __construct(
        private VendorDTO $vendor,
        private array     $stats = []
    ) {}

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            'vendor_id'            => $this->vendor->id,
            'store_name'           => $this->e($this->vendor->storeName),
            'store_slug'           => $this->attr($this->vendor->storeSlug),
            'store_description'    => wp_kses_post($this->vendor->storeDescription),
            'store_address'        => $this->e($this->vendor->storeAddress),
            'store_phone'          => $this->e($this->vendor->storePhone),
            'store_email'          => $this->e($this->vendor->storeEmail),
            'whatsapp_number'      => $this->e($this->vendor->whatsappNumber),
            'status'               => $this->vendor->status,
            'status_label'         => $this->getStatusLabel(),
            'status_class'         => $this->getStatusClass(),
            'is_trusted'           => $this->vendor->isTrusted,
            'balance'              => $this->money($this->vendor->balance),
            'balance_raw'          => $this->vendor->balance,
            'rating'               => number_format($this->vendor->rating, 1),
            'review_count'         => $this->vendor->reviewCount,
            'total_products'       => $this->vendor->totalProducts,
            'total_orders'         => $this->vendor->totalOrders,
            'total_sales'          => $this->money($this->vendor->totalSales),
            'subscription_plan'    => $this->e($this->vendor->subscriptionPlan),
            'subscription_status'  => $this->vendor->subscriptionStatus,
            'subscription_expiry'  => $this->formatDate($this->vendor->subscriptionExpiry),
            'store_url'            => $this->url(home_url('/store/' . $this->vendor->storeSlug)),
            'dashboard_url'        => $this->url(home_url('/vendor-dashboard/')),
            'logo_url'             => $this->getLogoUrl(),
            'banner_url'           => $this->getBannerUrl(),
            'stats'                => $this->stats,
        ];
    }

    /**
     * GetStatusLabel functionality helper.
     *
     * @return string Output payload.
     */
    private function getStatusLabel(): string
    {
        $labels = [
            'pending'  => __('قيد المراجعة', 'vmp'),
            'approved' => __('مفعّل', 'vmp'),
            'rejected' => __('مرفوض', 'vmp'),
            'banned'   => __('محظور', 'vmp'),
        ];
        return $labels[$this->vendor->status] ?? __('غير معروف', 'vmp');
    }

    /**
     * GetStatusClass functionality helper.
     *
     * @return string Output payload.
     */
    private function getStatusClass(): string
    {
        $classes = [
            'pending'  => 'vmp-status--warning',
            'approved' => 'vmp-status--success',
            'rejected' => 'vmp-status--danger',
            'banned'   => 'vmp-status--danger',
        ];
        return $classes[$this->vendor->status] ?? '';
    }

    /**
     * GetLogoUrl functionality helper.
     *
     * @return string Output payload.
     */
    private function getLogoUrl(): string
    {
        if ($this->vendor->storeLogo > 0) {
            $url = wp_get_attachment_image_url($this->vendor->storeLogo, 'thumbnail');
            return $url ? $this->url($url) : '';
        }
        return '';
    }

    /**
     * GetBannerUrl functionality helper.
     *
     * @return string Output payload.
     */
    private function getBannerUrl(): string
    {
        if ($this->vendor->storeBanner > 0) {
            $url = wp_get_attachment_image_url($this->vendor->storeBanner, 'large');
            return $url ? $this->url($url) : '';
        }
        return '';
    }

    /**
     * FormatDate functionality helper.
     *
     * @param ?string $date Description index.
     * @return string Output payload.
     */
    private function formatDate(?string $date): string
    {
        if (!$date) return __('غير محدد', 'vmp');
        $timestamp = strtotime($date);
        return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : $date;
    }
}
