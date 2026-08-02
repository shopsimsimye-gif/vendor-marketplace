<?php
namespace VMP\Jobs;

defined('ABSPATH') || exit;

use VMP\Core\Queue\JobInterface;
use VMP\Core\Container;
use VMP\Services\CommissionService;

/**
 * Class ProcessCommissionJob
 *
 * يعالج دفع العمولة لبائع في الخلفية بشكل غير متزامن
 */
class ProcessCommissionJob implements JobInterface
{
    public function __construct(
        private int   $commissionId,
        private int   $vendorId,
        private float $amount
    ) {}

    /**
     * Handle functionality helper.
     *
     * @return void Output payload.
     */
    public function handle(): void
    {
        /** @var CommissionService $commissionService */
        $commissionService = Container::getInstance()->make(CommissionService::class);

        $commissionService->markCommissionAsPaid($this->commissionId);
    }

    /**
     * GetPayload functionality helper.
     *
     * @return array Output payload.
     */
    public function getPayload(): array
    {
        return [
            'commission_id' => $this->commissionId,
            'vendor_id'     => $this->vendorId,
            'amount'        => $this->amount,
        ];
    }

    /**
     * FromPayload functionality helper.
     *
     * @param array $payload Description index.
     * @return self Output payload.
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            commissionId: (int) ($payload['commission_id'] ?? 0),
            vendorId:     (int) ($payload['vendor_id'] ?? 0),
            amount:       (float) ($payload['amount'] ?? 0.0)
        );
    }
}
