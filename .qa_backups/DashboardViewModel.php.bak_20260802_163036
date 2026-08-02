<?php
namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

use VMP\DTO\VendorDTO;

/**
 * DashboardViewModel — يُحضّر بيانات لوحة التحكم الكاملة للبائع
 *
 * يجمع: بيانات البائع + الإحصائيات + الطلبات الأخيرة + حالة الاشتراك
 */
class DashboardViewModel extends AbstractViewModel
{
    public function __construct(
        private VendorDTO $vendor,
        private array     $stats        = [],
        private array     $recentOrders = [],
        private array     $chartData    = []
    ) {}

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            // معلومات البائع الأساسية
            'vendor_id'           => $this->vendor->id,
            'store_name'          => $this->e($this->vendor->storeName),
            'store_url'           => $this->url(home_url('/store/' . $this->vendor->storeSlug)),
            'status'              => $this->vendor->status,
            'is_approved'         => $this->vendor->status === 'approved',
            'is_trusted'          => $this->vendor->isTrusted,

            // الرصيد والإحصائيات المالية
            'balance'             => $this->money($this->vendor->balance),
            'balance_raw'         => $this->vendor->balance,
            'total_sales'         => $this->money($this->vendor->totalSales),
            'total_orders'        => $this->vendor->totalOrders,
            'total_products'      => $this->vendor->totalProducts,

            // إحصائيات إضافية من الـ stats array
            'pending_orders'      => (int) ($this->stats['pending_orders'] ?? 0),
            'completed_orders'    => (int) ($this->stats['completed_orders'] ?? 0),
            'total_earnings'      => $this->money((float) ($this->stats['total_earnings'] ?? 0)),
            'pending_products'    => (int) ($this->stats['pending_products'] ?? 0),

            // الاشتراك
            'subscription_plan'   => $this->e($this->vendor->subscriptionPlan),
            'subscription_status' => $this->vendor->subscriptionStatus,
            'subscription_expiry' => $this->formatDate($this->vendor->subscriptionExpiry),
            'subscription_active' => $this->vendor->subscriptionStatus === 'active',
            'subscription_label'  => $this->getSubscriptionLabel(),
            'subscription_class'  => $this->getSubscriptionClass(),

            // الطلبات الأخيرة
            'recent_orders'       => $this->formatRecentOrders(),

            // بيانات الرسم البياني
            'chart_labels'        => $this->chartData['labels'] ?? [],
            'chart_earnings'      => $this->chartData['earnings'] ?? [],
            'chart_orders'        => $this->chartData['orders'] ?? [],

            // روابط الصفحات
            'urls' => [
                'products'      => $this->url(home_url('/vendor-dashboard/?vmp_page=products')),
                'add_product'   => $this->url(home_url('/vendor-dashboard/?vmp_page=add-product')),
                'orders'        => $this->url(home_url('/vendor-dashboard/?vmp_page=orders')),
                'withdrawals'   => $this->url(home_url('/vendor-dashboard/?vmp_page=withdrawals')),
                'subscriptions' => $this->url(home_url('/vendor-dashboard/?vmp_page=subscriptions')),
                'profile'       => $this->url(home_url('/vendor-dashboard/?vmp_page=profile')),
            ],
        ];
    }

    /**
     * GetSubscriptionLabel functionality helper.
     *
     * @return string Output payload.
     */
    private function getSubscriptionLabel(): string
    {
        $labels = [
            'active'   => __('نشط', 'vmp'),
            'expired'  => __('منتهي', 'vmp'),
            'inactive' => __('غير نشط', 'vmp'),
            'trial'    => __('تجريبي', 'vmp'),
        ];
        return $labels[$this->vendor->subscriptionStatus] ?? $this->vendor->subscriptionStatus;
    }

    /**
     * GetSubscriptionClass functionality helper.
     *
     * @return string Output payload.
     */
    private function getSubscriptionClass(): string
    {
        $classes = [
            'active'   => 'vmp-badge--success',
            'expired'  => 'vmp-badge--danger',
            'inactive' => 'vmp-badge--secondary',
            'trial'    => 'vmp-badge--info',
        ];
        return $classes[$this->vendor->subscriptionStatus] ?? '';
    }

    /**
     * FormatRecentOrders functionality helper.
     *
     * @return array Output payload.
     */
    private function formatRecentOrders(): array
    {
        $formatted = [];
        foreach ($this->recentOrders as $order) {
            $formatted[] = [
                'id'             => (int) ($order->id ?? 0),
                'parent_order_id'=> (int) ($order->parent_order_id ?? 0),
                'status'         => (string) ($order->status ?? ''),
                'total'          => $this->money((float) ($order->vendor_earnings ?? 0)),
                'created_at'     => $this->formatDate($order->created_at ?? null),
            ];
        }
        return $formatted;
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
