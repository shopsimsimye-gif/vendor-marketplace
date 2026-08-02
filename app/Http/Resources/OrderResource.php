<?php
namespace VMP\Http\Resources;

defined('ABSPATH') || exit;

/**
 * Order Resource — explicit allow-list for API responses.
 *
 * PUBLIC fields (buyer view):
 *   id, parent_order_id, status, total, created_at
 *
 * PRIVATE fields (vendor view):
 *   vendor_earnings, commission_amount, items
 */
class OrderResource
{
    /**
     * Transform order object into API-safe array.
     *
     * @param object $order Raw order object.
     * @param bool   $includePrivate Include earnings (vendor's own orders).
     * @return array
     */
    public static function toArray(object $order, bool $includePrivate = false): array
    {
        $data = [
            'id'              => (int) ($order->id ?? 0),
            'parent_order_id' => (int) ($order->parent_order_id ?? 0),
            'status'          => esc_attr($order->status ?? ''),
            'total'           => (float) ($order->total ?? 0),
            'created_at'      => esc_attr($order->created_at ?? ''),
        ];

        if ($includePrivate) {
            $data['vendor_earnings']  = (float) ($order->vendor_earnings ?? 0);
            $data['commission']       = (float) ($order->commission ?? 0);
            $data['commission_rate']  = (float) ($order->commission_rate ?? 0);
        }

        return $data;
    }

    /**
     * Transform a collection of orders.
     */
    public static function collection(array $orders, bool $includePrivate = false): array
    {
        return array_map(fn($o) => self::toArray($o, $includePrivate), $orders);
    }
}
