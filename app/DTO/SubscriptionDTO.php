<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * Class SubscriptionDTO
 *
 * Description of administrative platform component SubscriptionDTO.
 *
 * @package vendor-marketplace
 */
final class SubscriptionDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly int $vendorId = 0,
        public readonly int $planId = 0,
        public readonly string $status = 'active',
        public readonly float $amount = 0.0,
        public readonly string $billingPeriod = 'month',
        public readonly int $billingInterval = 1,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?string $trialEndDate = null,
        public readonly string $paymentMethod = '',
        public readonly array $paymentDetails = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {}

    /**
     * FromArray functionality helper.
     *
     * @param array $data Description index.
     * @return static Output payload.
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) ($data['id'] ?? 0),
            vendorId: (int) ($data['vendor_id'] ?? 0),
            planId: (int) ($data['plan_id'] ?? 0),
            status: (string) ($data['status'] ?? 'active'),
            amount: (float) ($data['amount'] ?? 0.0),
            billingPeriod: (string) ($data['billing_period'] ?? 'month'),
            billingInterval: (int) ($data['billing_interval'] ?? 1),
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            trialEndDate: $data['trial_end_date'] ?? null,
            paymentMethod: (string) ($data['payment_method'] ?? ''),
            paymentDetails: is_string($data['payment_details'] ?? null)
                ? (json_decode($data['payment_details'], true) ?: [])
                : (is_array($data['payment_details'] ?? null) ? $data['payment_details'] : []),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'vendor_id'        => $this->vendorId,
            'plan_id'          => $this->planId,
            'status'           => $this->status,
            'amount'           => $this->amount,
            'billing_period'   => $this->billingPeriod,
            'billing_interval' => $this->billingInterval,
            'start_date'       => $this->startDate,
            'end_date'         => $this->endDate,
            'trial_end_date'   => $this->trialEndDate,
            'payment_method'   => $this->paymentMethod,
            'payment_details'  => $this->paymentDetails,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
        ];
    }
}
