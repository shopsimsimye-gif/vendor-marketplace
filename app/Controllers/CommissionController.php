<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\CommissionService;
use VMP\Http\Requests\AdminGetCommissionsRequest;
use VMP\Http\Requests\PayCommissionRequest;
use VMP\Http\Requests\BulkPayCommissionsRequest;
use VMP\Http\Requests\AdminGetCommissionStatsRequest;
use VMP\Http\Requests\VendorGetCommissionsRequest;
use VMP\Http\Requests\VendorGetCommissionChartRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ApiResponse;

/**
 * Class CommissionController
 *
 * Description of administrative platform component CommissionController.
 *
 * @package vendor-marketplace
 */
class CommissionController extends BaseController
{
    public function __construct(
        private CommissionService $commissionService
    ) {}

    /**
     * جلب العمولات المعلقة (للمشرف)
     */
    public function adminGetCommissions(AdminGetCommissionsRequest $request): ApiResponse
    {
        $data = $request->validated();
        $limit = (int) ($data['limit'] ?? 50);

        $commissions = $this->commissionService->getPendingCommissions($limit);

        return new SuccessResponse(
            data: ['commissions' => $commissions],
            message: __('تم جلب العمولات بنجاح', 'vmp')
        );
    }

    /**
     * دفع عمولة واحدة (للمشرف)
     */
    public function payCommission(PayCommissionRequest $request): ApiResponse
    {
        $data = $request->validated();
        $this->commissionService->payCommission($data['commission_id']);

        return new SuccessResponse(
            message: __('تم تسجيل الدفع', 'vmp')
        );
    }

    /**
     * دفع عدة عمولات دفعة واحدة (للمشرف)
     */
    public function bulkPayCommissions(BulkPayCommissionsRequest $request): ApiResponse
    {
        $data = $request->validated();
        $count = $this->commissionService->bulkPayCommissions($data['ids']);

        return new SuccessResponse(
            data: ['count' => $count],
            message: sprintf(__('تم تسجيل دفع %d عمولة', 'vmp'), $count)
        );
    }

    /**
     * جلب إحصائيات عامة (للمشرف)
     */
    public function adminGetStats(AdminGetCommissionStatsRequest $request): ApiResponse
    {
        $stats = $this->commissionService->getAdminStats();

        return new SuccessResponse(
            data: ['stats' => $stats],
            message: __('تم جلب الإحصائيات بنجاح', 'vmp')
        );
    }

    /**
     * جلب عمولات البائع الحالي (للبائع)
     */
    public function vendorGetCommissions(VendorGetCommissionsRequest $request): ApiResponse
    {
        $data = $request->validated();
        
        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new \VMP\Exceptions\ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $args = [
            'status'    => $data['status'] ?? '',
            'date_from' => $data['date_from'] ?? '',
            'date_to'   => $data['date_to'] ?? '',
            'limit'     => 50,
        ];

        $commissions = $this->commissionService->getVendorCommissions($vendorId, $args);

        return new SuccessResponse(
            data: ['commissions' => $commissions],
            message: __('تم جلب العمولات بنجاح', 'vmp')
        );
    }

    /**
     * جلب إحصائيات الرسم البياني للبائع (للبائع)
     */
    public function vendorGetChart(VendorGetCommissionChartRequest $request): ApiResponse
    {
        $data = $request->validated();
        
        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new \VMP\Exceptions\ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $months = (int) $data['months'];
        $chartData = $this->commissionService->getVendorChartStats($vendorId, $months);

        return new SuccessResponse(
            data: ['chart_data' => $chartData],
            message: __('تم جلب الإحصائيات بنجاح', 'vmp')
        );
    }
}
