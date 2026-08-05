<?php
/**
 * OrderResource — تحويل بيانات الطلب لـ API (raw data)
 *
 * @package VMP\Http\Resources
 * @since 3.0.0
 */

namespace VMP\Http\Resources;

defined('ABSPATH') || exit;

class OrderResource
{
    /**
     * تحويل كائن الطلب إلى array للـ API
     *
     * @param object $order          كائن الطلب من DB
     * @param bool   $includePrivate تضمين البيانات الخاصة (earnings)
     * @return array
     */
    public static function toArray(object $order, bool $includePrivate = false): array
    {
        $parentOrderId = (int) ($order->parent_order_id ?? 0);
        $orderId       = (int) ($order->id ?? 0);

        // ✅ رقم الطلب من WooCommerce إن وجد
        $wcOrder = $parentOrderId > 0 ? wc_get_order($parentOrderId) : null;
        $orderNumber = $wcOrder ? $wcOrder->get_order_number() : ($order->order_number ?? '#' . $parentOrderId);

        $data = [
            'id'              => $orderId,
            'parent_order_id' => $parentOrderId,
            'order_number'    => (string) $orderNumber,
            'status'          => (string) ($order->status ?? 'pending'),
            'status_label'    => self::getStatusLabel((string) ($order->status ?? 'pending')),
            'total'           => (float) ($order->total ?? 0),
            'total_html'      => function_exists('wc_price') ? wc_price((float) ($order->total ?? 0)) : null,
            'created_at'      => self::formatDate($order->created_at ?? null),
            'customer'        => self::getCustomerData($wcOrder),
        ];

        if ($includePrivate) {
            $data['vendor_earnings']  = (float) ($order->vendor_earnings ?? 0);
            $data['vendor_earnings_html'] = function_exists('wc_price') ? wc_price((float) ($order->vendor_earnings ?? 0)) : null;
            $data['commission']       = (float) ($order->commission ?? 0);
            $data['commission_rate']  = (float) ($order->commission_rate ?? 0);
            $data['items']            = self::getOrderItems($wcOrder);
        }

        return $data;
    }

    /**
     * تحويل مجموعة من الطلبات
     */
    public static function collection(array $orders, bool $includePrivate = false): array
    {
        return array_map(static fn($o) => self::toArray($o, $includePrivate), $orders);
    }

    /**
     * تسمية حالة الطلب
     */
    private static function getStatusLabel(string $status): string
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

    /**
     * بيانات العميل (مُختصرة)
     */
    private static function getCustomerData($wcOrder): ?array
    {
        if (!$wcOrder) {
            return null;
        }

        return [
            'id'    => (int) $wcOrder->get_customer_id(),
            'name'  => (string) ($wcOrder->get_formatted_billing_full_name() ?: __('زائر', 'vmp')),
            'email' => (string) $wcOrder->get_billing_email(),
        ];
    }

    /**
     * عناصر الطلب (للعرض الخاص)
     */
    private static function getOrderItems($wcOrder): array
    {
        if (!$wcOrder) {
            return [];
        }

        $items = [];
        foreach ($wcOrder->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'product_id'   => (int) $item->get_product_id(),
                'product_name' => (string) $item->get_name(),
                'quantity'     => (int) $item->get_quantity(),
                'subtotal'     => (float) $item->get_subtotal(),
                'total'        => (float) $item->get_total(),
                'image_url'    => $product ? (string) wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
            ];
        }

        return $items;
    }
}
