<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\Contracts\OrderRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;

/**
 * Class GetOrderDetailsRequest
 *
 * ✅ يسمح للمدير أو للبائع صاحب الطلب فقط
 * ✅ لا يستخدم $_POST مباشرة
 * ✅ يستخدم Repository بدلاً من استعلام DB مباشر
 *
 * @package vendor-marketplace
 */
class GetOrderDetailsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        // المدير يملك الصلاحية دائماً
        if (current_user_can('vmp_manage_orders')) {
            return true;
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return false;
        }

        $vendorOrderId = (int) $this->input('vendor_order_id', 0);
        if ($vendorOrderId <= 0) {
            return false;
        }

        // جلب البائع عبر Repository (بدلاً من استعلام DB مباشر)
        /** @var VendorRepositoryInterface $vendorRepo */
        $vendorRepo = \VMP\Core\Container::getInstance()->make(VendorRepositoryInterface::class);
        $vendor = $vendorRepo->findByUserId($userId);

        if (!$vendor || $vendor->id <= 0) {
            return false;
        }

        // التحقق من ملكية الطلب
        /** @var OrderRepositoryInterface $orderRepo */
        $orderRepo = \VMP\Core\Container::getInstance()->make(OrderRepositoryInterface::class);
        $order = $orderRepo->find($vendorOrderId);

        return $order && (int) $order->vendor_id === (int) $vendor->id;
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_order_id' => ['required', 'integer', 'min:1'],
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
