<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\OrderService;
use VMP\Http\Requests\AdminGetVendorOrdersRequest;
use VMP\Http\Requests\GetOrderDetailsRequest;
use VMP\Http\Requests\GetVendorOrdersRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ApiResponse;

/**
 * Class OrderController
 *
 * Description of administrative platform component OrderController.
 *
 * @package vendor-marketplace
 */
class OrderController extends BaseController
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * جلب طلبات بائع (للمشرف)
     */
    public function adminGetVendorOrders(AdminGetVendorOrdersRequest $request): ApiResponse
    {
        $data = $request->validated();
        
        $vendorId = (int) ($data['vendor_id'] ?? 0);
        $args = [
            'status' => $data['status'] ?? '',
            'limit'  => (int) ($data['limit'] ?? 20),
            'offset' => (int) ($data['offset'] ?? 0),
        ];

        $orders = $this->orderService->getVendorOrders($vendorId, $args);

        return new SuccessResponse(
            data: ['orders' => $orders],
            message: __('تم جلب الطلبات بنجاح', 'vmp')
        );
    }

    /**
     * جلب تفاصيل طلب فرعي (للمشرف/البائع)
     */
    public function getOrderDetails(GetOrderDetailsRequest $request): ApiResponse
    {
        $data = $request->validated();
        $vendorOrderId = (int) $data['vendor_order_id'];

        $details = $this->orderService->getOrderDetails($vendorOrderId);

        return new SuccessResponse(
            data: $details,
            message: __('تم جلب تفاصيل الطلب بنجاح', 'vmp')
        );
    }

    /**
     * جلب طلبات البائع الحالي (للبائع)
     */
    public function getVendorOrders(GetVendorOrdersRequest $request): ApiResponse
    {
        $data = $request->validated();
        
        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new \VMP\Exceptions\ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $args = [
            'status' => $data['status'] ?? '',
            'limit'  => (int) ($data['limit'] ?? 20),
            'offset' => (int) ($data['offset'] ?? 0),
        ];

        $orders = $this->orderService->getVendorOrders($vendorId, $args);

        return new SuccessResponse(
            data: ['orders' => $orders],
            message: __('تم جلب الطلبات بنجاح', 'vmp')
        );
    }
}
