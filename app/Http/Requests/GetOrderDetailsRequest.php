<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\Contracts\OrderRepositoryInterface;

/**
 * Class GetOrderDetailsRequest
 *
 * Description of administrative platform component GetOrderDetailsRequest.
 *
 * @package vendor-marketplace
 */
class GetOrderDetailsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * ✅ يسمح للأدمن أو للبائع الذي يملك الطلب (وليس فقط للأدمن)
     * ✅ يمنع البائع من جلب تفاصيل طلبات الآخرين (إصلاح ثغرة أمنية)
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        // الأدمن يملك الصلاحية دائماً
        if (current_user_can('vmp_manage_orders')) {
            return true;
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return false;
        }

        // البائع: يجب أن يملك الطلب
        $vendorOrderId = (int) ($_POST['vendor_order_id'] ?? 0);
        if (!$vendorOrderId) {
            return false;
        }

        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);
        if (!$vendorId) {
            // Fallback: البحث عن البائع عبر الجدول
            global $wpdb;
            $vendorId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
                $userId
            ));
        }

        if (!$vendorId) {
            return false;
        }

        /** @var OrderRepositoryInterface $orderRepo */
        $orderRepo = \VMP\Core\Container::getInstance()->make(OrderRepositoryInterface::class);
        $order = $orderRepo->find($vendorOrderId);

        return $order && (int) $order->vendor_id === $vendorId;
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_order_id' => ['required', 'integer'],
        ];
    }

    /**
     * Attributes functionality helper.
     *
     * @return array Output payload.
     */
    protected function attributes(): array
    {
        return [
            'vendor_order_id' => __('معرف طلب البائع', 'vmp'),
        ];
    }
}
