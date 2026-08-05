<?php
/**
 * ProductViewModel — يُحضّر بيانات المنتج للعرض في القوالب
 *
 * @package VMP\Http\ViewModels
 * @since 3.0.0
 */

namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

use VMP\DTO\ProductDTO;

class ProductViewModel extends AbstractViewModel
{
    public function __construct(
        private ProductDTO $product
    ) {}

    /**
     * تحويل البيانات إلى array جاهز للعرض
     */
    public function toArray(): array
    {
        $productId = (int) ($this->product->productId ?? $this->product->id ?? 0);

        return [
            'id'                => (int) ($this->product->id ?? 0),
            'product_id'        => $productId,
            'vendor_id'         => (int) ($this->product->vendorId ?? 0),
            'title'             => $this->e((string) ($this->product->title ?? '')),
            'description'       => wp_kses_post((string) ($this->product->description ?? '')),
            'short_description' => wp_kses_post((string) ($this->product->shortDescription ?? '')),
            'regular_price'     => $this->money((float) ($this->product->regularPrice ?? 0)),
            'regular_price_raw' => (float) ($this->product->regularPrice ?? 0),
            'sale_price'        => $this->getSalePriceFormatted(),
            'sale_price_raw'    => (float) ($this->product->salePrice ?? 0),
            'effective_price'   => $this->money($this->getEffectivePrice()),
            'sku'               => $this->e((string) ($this->product->sku ?? '')),
            'stock_status'      => (string) ($this->product->stockStatus ?? 'instock'),
            'stock_status_label'=> $this->getStockLabel(),
            'stock_quantity'    => (int) ($this->product->stockQuantity ?? 0),
            'status'            => (string) ($this->product->status ?? 'pending'),
            'status_label'      => $this->getStatusLabel(),
            'status_class'      => $this->getStatusClass(),
            'image_url'         => $this->getImageUrl(),
            'gallery_urls'      => $this->getGalleryUrls(),
            'edit_url'          => $this->url($this->getEditUrl($productId)),
            'admin_url'         => $this->url(admin_url('post.php?post=' . $productId . '&action=edit')),
            'created_at'        => $this->formatDate($this->product->createdAt ?? null),
        ];
    }

    /**
     * السعر الفعّال (التخفيض أو العادي)
     */
    private function getEffectivePrice(): float
    {
        $salePrice = (float) ($this->product->salePrice ?? 0);
        $regularPrice = (float) ($this->product->regularPrice ?? 0);

        return $salePrice > 0 ? $salePrice : $regularPrice;
    }

    /**
     * تنسيق سعر التخفيض (فارغ إذا لا يوجد)
     */
    private function getSalePriceFormatted(): string
    {
        $salePrice = (float) ($this->product->salePrice ?? 0);
        return $salePrice > 0 ? $this->money($salePrice) : '';
    }

    /**
     * تسمية حالة المنتج
     */
    private function getStatusLabel(): string
    {
        $status = (string) ($this->product->status ?? 'pending');

        $labels = [
            'pending'  => __('قيد المراجعة', 'vmp'),
            'approved' => __('منشور', 'vmp'),
            'rejected' => __('مرفوض', 'vmp'),
            'draft'    => __('مسودة', 'vmp'),
            'trash'    => __('محذوف', 'vmp'),
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * CSS class لحالة المنتج
     */
    private function getStatusClass(): string
    {
        $status = (string) ($this->product->status ?? 'pending');

        $classes = [
            'pending'  => 'vmp-badge--warning',
            'approved' => 'vmp-badge--success',
            'rejected' => 'vmp-badge--danger',
            'draft'    => 'vmp-badge--secondary',
            'trash'    => 'vmp-badge--muted',
        ];

        return $classes[$status] ?? '';
    }

    /**
     * تسمية حالة المخزون
     */
    private function getStockLabel(): string
    {
        $status = (string) ($this->product->stockStatus ?? 'instock');

        $labels = [
            'instock'     => __('متوفر', 'vmp'),
            'outofstock'  => __('نفد المخزون', 'vmp'),
            'onbackorder' => __('طلب مسبق', 'vmp'),
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * رابط صورة المنتج الرئيسية
     */
    private function getImageUrl(): string
    {
        $imageId = (int) ($this->product->imageId ?? 0);

        if ($imageId > 0 && wp_attachment_is_image($imageId)) {
            $url = wp_get_attachment_image_url($imageId, 'woocommerce_thumbnail');
            if ($url) {
                return $this->url($url);
            }
        }

        // ✅ صورة افتراضية WooCommerce
        return function_exists('wc_placeholder_img_src')
            ? $this->url(wc_placeholder_img_src())
            : '';
    }

    /**
     * روابط معرض الصور
     */
    private function getGalleryUrls(): array
    {
        $galleryIds = $this->product->galleryImageIds ?? [];
        if (!is_array($galleryIds) || empty($galleryIds)) {
            return [];
        }

        $urls = [];
        foreach ($galleryIds as $imageId) {
            $id = (int) $imageId;
            if ($id > 0 && wp_attachment_is_image($id)) {
                $url = wp_get_attachment_image_url($id, 'woocommerce_thumbnail');
                if ($url) {
                    $urls[] = $this->url($url);
                }
            }
        }

        return $urls;
    }

    /**
     * رابط تعديل المنتج في لوحة البائع
     */
    private function getEditUrl(int $productId): string
    {
        $settings = get_option('vmp_settings', []);
        $pageId   = (int) ($settings['display']['dashboard_page'] ?? 0);
        $baseUrl  = $pageId && get_post($pageId) ? get_permalink($pageId) : home_url('/vendor-dashboard/');

        return add_query_arg([
            'vmp_page' => 'edit-product',
            'id'       => $productId,
        ], trailingslashit($baseUrl));
    }

    /**
     * تنسيق التاريخ
     */
    private function formatDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return (string) $date;
        }

        return wp_date(get_option('date_format'), $timestamp) ?: (string) $date;
    }
}
