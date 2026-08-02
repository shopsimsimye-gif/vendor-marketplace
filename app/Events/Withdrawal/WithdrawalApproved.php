<?php
namespace VMP\Events\Withdrawal;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند موافقة المشرف على طلب سحب وتحويل الأموال للبائع
 */
class WithdrawalApproved extends AbstractEvent
{
    public function __construct(
        public readonly int    $withdrawalId,
        public readonly int    $vendorId,
        public readonly float  $amount,
        public readonly string $method,
        public readonly string $reference = ''
    ) {
        parent::__construct();
    }

    /**
     * GetName functionality helper.
     *
     * @return string Output payload.
     */
    public function getName(): string
    {
        return 'withdrawal.approved';
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'withdrawal_id' => $this->withdrawalId,
            'vendor_id'     => $this->vendorId,
            'amount'        => $this->amount,
            'method'        => $this->method,
            'reference'     => $this->reference,
        ]);
    }
}
