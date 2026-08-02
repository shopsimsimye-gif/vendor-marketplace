<?php
namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

use VMP\DTO\ProductDTO;

/**
 * ProductViewModel — يُحضّر بيانات المنتج للعرض في القوالب
 */
class ProductViewModel extends AbstractViewModel
{
    public function __construct(
        private ProductDTO $product
    ) {}

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            'id'                => $this->product->id,
            'product_id'        => $this->product->productId,
            'vendor_id'         => $this->product->vendorId,
            'title'             => $this->e($this->product->title),
            'description'       => wp_kses_post($this->product->description),
            'short_description' => wp_kses_post($this->product->shortDescription),
            'regular_price'     => $this->money($this->product->regularPrice),
            'regular_price_raw' => $this->product->regularPrice,
            'sale_price'        => $this->product->salePrice > 0 ? $this->money($this->product->salePrice) : '',
            'sale_price_raw'    => $this->product->salePrice,
            'effective_price'   => $this->money($this->getEffectivePrice()),
            'sku'               => $this->e($this->product->sku),
            'stock_status'      => $this->product->stockStatus,
            'stock_status_label'=> $this->getStockLabel(),
            'stock_quantity'    => $this->product->stockQuantity,
            'status'            => $this->product->status,
            'status_label'      => $this->getStatusLabel(),
            'status_class'      => $this->getStatusClass(),
            'image_url'         => $this->getImageUrl(),
            'gallery_urls'      => $this->getGalleryUrls(),
            'edit_url'          => $this->url(home_url('/vendor-dashboard/?vmp_page=edit-product&id=' . $this->product->productId)),
            'admin_url'         => $this->url(admin_url('post.php?post=' . $this->product->productId . '&action=edit')),
            'created_at'        => $this->formatDate($this->product->createdAt),
        ];
    }

    /**
     * GetEffectivePrice functionality helper.
     *
     * @return float Output payload.
     */
    private function getEffectivePrice(): float
    {
        return $this->product->salePrice > 0
            ? $this->product->salePrice
            : $this->product->regularPrice;
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
            'approved' => __('منشور', 'vmp'),
            'rejected' => __('مرفوض', 'vmp'),
            'draft'    => __('مسودة', 'vmp'),
        ];
        return $labels[$this->product->status] ?? $this->product->status;
    }

    /**
     * GetStatusClass functionality helper.
     *
     * @return string Output payload.
     */
    private function getStatusClass(): string
    {
        $classes = [
            'pending'  => 'vmp-badge--warning',
            'approved' => 'vmp-badge--success',
            'rejected' => 'vmp-badge--danger',
            'draft'    => 'vmp-badge--secondary',
        ];
        return $classes[$this->product->status] ?? '';
    }

    /**
     * GetStockLabel functionality helper.
     *
     * @return string Output payload.
     */
    private function getStockLabel(): string
    {
        $labels = [
            'instock'    => __('متوفر', 'vmp'),
            'outofstock' => __('نفد المخزون', 'vmp'),
            'onbackorder'=> __('طلب مسبق', 'vmp'),
        ];
        return $labels[$this->product->stockStatus] ?? $this->product->stockStatus;
    }

    /**
     * GetImageUrl functionality helper.
     *
     * @return string Output payload.
     */
    private function getImageUrl(): string
    {
        if ($this->product->imageId > 0) {
            $url = wp_get_attachment_image_url($this->product->imageId, 'woocommerce_thumbnail');
            return $url ? $this->url($url) : '';
        }
        return function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '';
    }

    /**
     * GetGalleryUrls functionality helper.
     *
     * @return array Output payload.
     */
    private function getGalleryUrls(): array
    {
        $urls = [];
        foreach ($this->product->galleryImageIds as $imageId) {
            $url = wp_get_attachment_image_url((int) $imageId, 'woocommerce_thumbnail');
            if ($url) {
                $urls[] = $this->url($url);
            }
        }
        return $urls;
    }

    /**
     * FormatDate functionality helper.
     *
     * @param ?string $date Description index.
     * @return string Output payload.
     */
    private function formatDate(?string $date): string
    {
        if (!$date) return '';
        $timestamp = strtotime($date);
        return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : $date;
    }
}
