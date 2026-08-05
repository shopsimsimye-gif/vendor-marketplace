<?php
/**
 * DashboardViewModel — يُحضّر بيانات لوحة التحكم الكاملة للبائع
 *
 * @package VMP\Http\ViewModels
 * @since 3.0.0
 */

namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

use VMP\DTO\VendorDTO;

class DashboardViewModel extends AbstractViewModel
{
    public function __construct(
        private VendorDTO $vendor,
        private array     $stats        = [],
        private array     $recentOrders = [],
        private array     $chartData    = []
    ) {}

    /**
     * تحويل البيانات إلى array
     */
    public function toArray(): array
    {
        return [
            // معلومات البائع الأساسية
            'vendor_id'           => (int) $this->vendor->id,
            'store_name'          => $this->e((string) ($this->vendor->storeName ?? '')),
            'store_url'           => $this->url($this->getStoreUrl()),
            'status'              => (string) ($this->vendor->status ?? 'pending'),
            'is_approved'         => ($this->vendor->status ?? '') === 'approved',
            'is_trusted'          => !empty($this->vendor->isTrusted),

            // الرصيد والإحصائيات المالية
            'balance'             => $this->money((float) ($this->vendor->balance ?? 0)),
            'balance_raw'         => (float) ($this->vendor->balance ?? 0),
            'total_sales'         => $this->money((float) ($this->vendor->totalSales ?? 0)),
            'total_orders'        => (int) ($this->vendor->totalOrders ?? 0),
            'total_products'      => (int) ($this->vendor->totalProducts ?? 0),

            // إحصائيات إضافية
            'pending_orders'      => (int) ($this->stats['pending_orders'] ?? 0),
            'completed_orders'    => (int) ($this->stats['completed_orders'] ?? 0),
            'total_earnings'      => $this->money((float) ($this->stats['total_earnings'] ?? 0)),
            'pending_products'    => (int) ($this->stats['pending_products'] ?? 0),

            // الاشتراك
            'subscription_plan'   => $this->e((string) ($this->vendor->subscriptionPlan ?? '')),
            'subscription_status' => (string) ($this->vendor->subscriptionStatus ?? 'inactive'),
            'subscription_expiry' => $this->formatDate($this->vendor->subscriptionExpiry ?? null),
            'subscription_active' => ($this->vendor->subscriptionStatus ?? '') === 'active',
            'subscription_label'  => $this->getSubscriptionLabel(),
            'subscription_class'  => $this->getSubscriptionClass(),

            // الطلبات الأخيرة
            'recent_orders'       => $this->formatRecentOrders(),

            // بيانات الرسم البياني
            'chart_labels'        => $this->chartData['labels'] ?? [],
            'chart_earnings'      => $this->chartData['earnings'] ?? [],
            'chart_orders'        => $this->chartData['orders'] ?? [],

            // روابط الصفحات (ديناميكية)
            'urls' => $this->getDashboardUrls(),
        ];
    }

    /**
     * تسمية حالة الاشتراك
     */
    private function getSubscriptionLabel(): string
    {
        $status = (string) ($this->vendor->subscriptionStatus ?? 'inactive');

        $labels = [
            'active'   => __('نشط', 'vmp'),
            'expired'  => __('منتهي', 'vmp'),
            'inactive' => __('غير نشط', 'vmp'),
            'trial'    => __('تجريبي', 'vmp'),
            'pending'  => __('معلق', 'vmp'),
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * CSS class لحالة الاشتراك
     */
    private function getSubscriptionClass(): string
    {
        $status = (string) ($this->vendor->subscriptionStatus ?? 'inactive');

        $classes = [
            'active'   => 'vmp-badge--success',
            'expired'  => 'vmp-badge--danger',
            'inactive' => 'vmp-badge--secondary',
            'trial'    => 'vmp-badge--info',
            'pending'  => 'vmp-badge--warning',
        ];

        return $classes[$status] ?? '';
    }

    /**
     * تنسيق الطلبات الأخيرة
     */
    private function formatRecentOrders(): array
    {
        $formatted = [];

        foreach ($this->recentOrders as $order) {
            $orderId       = (int) ($order->id ?? 0);
            $parentOrderId = (int) ($order->parent_order_id ?? 0);
            $wcOrderId     = $parentOrderId > 0 ? $parentOrderId : $orderId;

            $formatted[] = [
                'id'              => $orderId,
                'parent_order_id' => $parentOrderId,
                'order_number'    => !empty($order->order_number) ? (string) $order->order_number : '#' . $wcOrderId,
                'status'          => (string) ($order->status ?? ''),
                'status_label'    => $this->getOrderStatusLabel((string) ($order->status ?? '')),
                'total'           => $this->money((float) ($order->total ?? 0)),
                'earnings'        => $this->money((float) ($order->vendor_earnings ?? 0)),
                'earnings_raw'    => (float) ($order->vendor_earnings ?? 0),
                'created_at'      => $this->formatDate($order->created_at ?? null),
                'order_url'       => $wcOrderId > 0 ? $this->url(get_edit_post_link($wcOrderId, 'raw')) : '',
            ];
        }

        return $formatted;
    }

    /**
     * تسمية حالة الطلب
     */
    private function getOrderStatusLabel(string $status): string
    {
        $labels = [
            'pending'    => __('قيد الانتظار', 'vmp'),
            'processing' => __('قيد المعالجة', 'vmp'),
            'on-hold'    => __('معلق', 'vmp'),
            'completed'  => __('مكتمل', 'vmp'),
            'cancelled'  => __('ملغي', 'vmp'),
            'refunded'   => __('مسترجع', 'vmp'),
            'failed'     => __('فاشل', 'vmp'),
        ];

        return $labels[$status] ?? $status;
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

        // ✅ استخدام wp_date بدلاً من date_i18n (الأحدث في WP 5.3+)
        return wp_date(get_option('date_format'), $timestamp) ?: (string) $date;
    }

    /**
     * رابط المتجر العام
     */
    private function getStoreUrl(): string
    {
        $storeBase = get_option('vmp_store_base', 'store');
        $slug      = !empty($this->vendor->storeSlug)
            ? (string) $this->vendor->storeSlug
            : 'vendor-' . (int) $this->vendor->id;

        return home_url('/' . trailingslashit($storeBase) . $slug . '/');
    }

    /**
     * روابط لوحة التحكم (ديناميكية)
     */
    private function getDashboardUrls(): array
    {
        $dashboardPageId = (int) (get_option('vmp_settings')['display']['dashboard_page'] ?? 0);
        $baseUrl         = $dashboardPageId && get_post($dashboardPageId)
            ? get_permalink($dashboardPageId)
            : home_url('/vendor-dashboard/');

        $baseUrl = trailingslashit($baseUrl);

        $pages = [
            'products'      => 'products',
            'add_product'   => 'add-product',
            'orders'        => 'orders',
            'withdrawals'   => 'withdrawals',
            'subscriptions' => 'subscriptions',
            'profile'       => 'profile',
        ];

        $urls = [];
        foreach ($pages as $key => $page) {
            $urls[$key] = $this->url(add_query_arg('vmp_page', $page, $baseUrl));
        }

        return $urls;
    }
}
