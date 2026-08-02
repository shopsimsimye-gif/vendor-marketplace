<?php
namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

use VMP\DTO\OrderDTO;

/**
 * OrderViewModel — يُحضّر بيانات الطلب للعرض في القوالب
 */
class OrderViewModel extends AbstractViewModel
{
    public function __construct(
        private OrderDTO $order
    ) {}

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            'id'              => $this->order->id,
            'order_id'        => $this->order->orderId,
            'parent_order_id' => $this->order->parentOrderId,
            'vendor_id'       => $this->order->vendorId,
            'status'          => $this->order->status,
            'status_label'    => $this->getStatusLabel(),
            'status_class'    => $this->getStatusClass(),
            'total'           => $this->money($this->order->total),
            'total_raw'       => $this->order->total,
            'commission'      => $this->money($this->order->commission),
            'commission_raw'  => $this->order->commission,
            'vendor_earnings' => $this->money($this->order->vendorEarnings),
            'vendor_earnings_raw' => $this->order->vendorEarnings,
            'created_at'      => $this->formatDate($this->order->createdAt),
            'admin_url'       => $this->url(admin_url('post.php?post=' . $this->order->parentOrderId . '&action=edit')),
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
            'pending'    => __('قيد الانتظار', 'vmp'),
            'processing' => __('قيد المعالجة', 'vmp'),
            'completed'  => __('مكتمل', 'vmp'),
            'cancelled'  => __('ملغي', 'vmp'),
            'refunded'   => __('مسترجع', 'vmp'),
            'on-hold'    => __('معلّق', 'vmp'),
        ];
        return $labels[$this->order->status] ?? $this->order->status;
    }

    /**
     * GetStatusClass functionality helper.
     *
     * @return string Output payload.
     */
    private function getStatusClass(): string
    {
        $classes = [
            'pending'    => 'vmp-badge--warning',
            'processing' => 'vmp-badge--info',
            'completed'  => 'vmp-badge--success',
            'cancelled'  => 'vmp-badge--danger',
            'refunded'   => 'vmp-badge--secondary',
            'on-hold'    => 'vmp-badge--warning',
        ];
        return $classes[$this->order->status] ?? '';
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
