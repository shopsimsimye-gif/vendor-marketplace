<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\WithdrawalService;
use VMP\Contracts\WithdrawalRepositoryInterface;
use VMP\Http\Requests\RequestWithdrawalRequest;
use VMP\Http\Requests\AdminGetWithdrawalsRequest;
use VMP\Http\Requests\AdminProcessWithdrawalRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ApiResponse;
use VMP\Exceptions\ServiceException;

/**
 * Class WithdrawalController
 *
 * Description of administrative platform component WithdrawalController.
 *
 * @package vendor-marketplace
 */
class WithdrawalController extends BaseController
{
    public function __construct(
        private WithdrawalService $withdrawalService,
        private WithdrawalRepositoryInterface $withdrawalRepository
    ) {}

    /**
     * طلب سحب أرباح (للبائع)
     */
    public function requestWithdrawal(RequestWithdrawalRequest $request): ApiResponse
    {
        $data = $request->validated();

        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $method = sanitize_text_field($data['method'] ?? 'bank_transfer');
        $methodDetails = (array) ($data['method_details'] ?? []);

        $withdrawalId = $this->withdrawalService->requestWithdrawal(
            $vendorId,
            (float) $data['amount'],
            $method,
            $methodDetails
        );

        return new SuccessResponse(
            data: ['withdrawal_id' => $withdrawalId],
            message: __('تم تقديم طلب السحب بنجاح', 'vmp')
        );
    }

    /**
     * جلب طلبات السحب المعلقة (للمشرف)
     */
    public function adminGetWithdrawals(AdminGetWithdrawalsRequest $request): ApiResponse
    {
        $data = $request->validated();
        $limit = (int) ($data['limit'] ?? 100);
        $withdrawals = $this->withdrawalRepository->getPending($limit);

        return new SuccessResponse(
            data: ['withdrawals' => $withdrawals],
            message: __('تم جلب طلبات السحب بنجاح', 'vmp')
        );
    }

    /**
     * معالجة طلب سحب — موافقة أو رفض (للمشرف)
     */
    public function adminProcessWithdrawal(AdminProcessWithdrawalRequest $request): ApiResponse
    {
        $data = $request->validated();
        $withdrawalId = (int) $data['withdrawal_id'];
        $actionType   = sanitize_text_field($data['action_type']);
        $reason       = sanitize_textarea_field($data['reason'] ?? '');
        $processedBy  = get_current_user_id();

        if ($actionType === 'approve') {
            $this->withdrawalService->approveWithdrawal($withdrawalId, $processedBy);
            return new SuccessResponse(message: __('تم الموافقة على السحب', 'vmp'));
        }

        if ($actionType === 'reject') {
            $this->withdrawalService->rejectWithdrawal($withdrawalId, $processedBy, $reason);
            return new SuccessResponse(message: __('تم رفض السحب وإرجاع المبلغ', 'vmp'));
        }

        throw new ServiceException(__('نوع الإجراء غير مدعوم', 'vmp'));
    }
}
