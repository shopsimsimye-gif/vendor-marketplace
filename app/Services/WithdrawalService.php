<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\WithdrawalRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\EventManager;
use VMP\Core\Logger;
use Exception;

/**
 * Class WithdrawalService
 *
 * Description of administrative platform component WithdrawalService.
 *
 * @package vendor-marketplace
 */
class WithdrawalService
{
    public function __construct(
        private WithdrawalRepositoryInterface $withdrawalRepository,
        private VendorRepositoryInterface $vendorRepository,
        private EventManager $eventManager,
        private Logger $logger
    ) {}

    /**
     * طلب سحب أرباح للبائع
     *
     * @param int $vendorId معرف البائع
     * @param float $amount المبلغ المطلوب
     * @param string $method طريقة السحب
     * @param array $methodDetails تفاصيل الطريقة
     * @return int معرف طلب السحب
     * @throws Exception
     */
    public function requestWithdrawal(int $vendorId, float $amount, string $method = 'bank_transfer', array $methodDetails = []): int
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            throw new Exception(__('البائع غير موجود', 'vmp'));
        }

        $minWithdrawal = (float) get_option('vmp_min_withdrawal', 50);
        if ($amount <= 0) {
            throw new Exception(__('المبلغ غير صالح', 'vmp'));
        }
        if ($amount < $minWithdrawal) {
            throw new Exception(sprintf(__('الحد الأدنى للسحب هو %s', 'vmp'), $minWithdrawal));
        }
        if ($amount > $vendor->balance) {
            throw new Exception(__('رصيدك غير كافٍ', 'vmp'));
        }

        $withdrawalId = $this->withdrawalRepository->create([
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'method' => sanitize_text_field($method),
            'method_details' => $methodDetails,
        ]);

        if (!$withdrawalId) {
            throw new Exception(__('حدث خطأ أثناء تقديم طلب السحب', 'vmp'));
        }

        // خصم المبلغ من رصيد البائع فور تقديم الطلب
        $this->vendorRepository->updateBalance($vendorId, -$amount);

        try {
            $this->eventManager->trigger(
                'vmp_withdrawal_requested', 
                $withdrawalId, 
                $vendorId, 
                $amount
            );
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث طلب السحب: ' . $e->getMessage());
        }

        return $withdrawalId;
    }

    /**
     * الموافقة على طلب السحب
     *
     * @param int $withdrawalId
     * @param int $processedBy
     * @return void
     * @throws Exception
     */
    public function approveWithdrawal(int $withdrawalId, int $processedBy): void
    {
        $withdrawal = $this->withdrawalRepository->find($withdrawalId);
        if (!$withdrawal) {
            throw new Exception(__('طلب السحب غير موجود', 'vmp'));
        }

        if ($withdrawal->status === 'approved') {
            throw new Exception(__('تمت الموافقة على طلب السحب مسبقاً', 'vmp'));
        }

        if (!$this->withdrawalRepository->approve($withdrawalId, $processedBy)) {
            throw new Exception(__('فشل في الموافقة على طلب السحب', 'vmp'));
        }

        try {
            $this->eventManager->trigger(
                'vmp_withdrawal_approved', 
                $withdrawalId, 
                $withdrawal->vendor_id, 
                $withdrawal->amount
            );
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث الموافقة على السحب: ' . $e->getMessage());
        }
    }

    /**
     * رفض طلب السحب واسترجاع الرصيد
     *
     * @param int $withdrawalId
     * @param int $processedBy
     * @param string $reason
     * @return void
     * @throws Exception
     */
    public function rejectWithdrawal(int $withdrawalId, int $processedBy, string $reason = ''): void
    {
        $withdrawal = $this->withdrawalRepository->find($withdrawalId);
        if (!$withdrawal) {
            throw new Exception(__('طلب السحب غير موجود', 'vmp'));
        }

        if ($withdrawal->status === 'rejected') {
            throw new Exception(__('تم رفض طلب السحب مسبقاً', 'vmp'));
        }

        // استرجاع المبلغ إلى رصيد البائع
        $this->vendorRepository->updateBalance($withdrawal->vendor_id, $withdrawal->amount);

        if (!$this->withdrawalRepository->reject($withdrawalId, $processedBy, sanitize_textarea_field($reason))) {
            throw new Exception(__('فشل في رفض طلب السحب', 'vmp'));
        }

        try {
            $this->eventManager->trigger(
                'vmp_withdrawal_rejected', 
                $withdrawalId, 
                $withdrawal->vendor_id, 
                $reason
            );
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث رفض السحب: ' . $e->getMessage());
        }
    }
}
