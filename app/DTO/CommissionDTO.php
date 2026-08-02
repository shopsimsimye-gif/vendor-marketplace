<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * Class CommissionDTO
 *
 * Description of administrative platform component CommissionDTO.
 *
 * @package vendor-marketplace
 */
final class CommissionDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly int $vendorId = 0,
        public readonly int $orderId = 0,
        public readonly int $vendorOrderId = 0,
        public readonly int $productId = 0,
        public readonly float $amount = 0.0,
        public readonly float $commissionRate = 0.0,
        public readonly float $commissionAmount = 0.0,
        public readonly float $vendorAmount = 0.0,
        public readonly string $status = 'pending',
        public readonly ?string $paidAt = null,
        public readonly ?string $createdAt = null
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
            orderId: (int) ($data['order_id'] ?? 0),
            vendorOrderId: (int) ($data['vendor_order_id'] ?? 0),
            productId: (int) ($data['product_id'] ?? 0),
            amount: (float) ($data['amount'] ?? 0.0),
            commissionRate: (float) ($data['commission_rate'] ?? 0.0),
            commissionAmount: (float) ($data['commission_amount'] ?? 0.0),
            vendorAmount: (float) ($data['vendor_amount'] ?? 0.0),
            status: (string) ($data['status'] ?? 'pending'),
            paidAt: $data['paid_at'] ?? null,
            createdAt: $data['created_at'] ?? null
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
            'id'                => $this->id,
            'vendor_id'         => $this->vendorId,
            'order_id'          => $this->orderId,
            'vendor_order_id'   => $this->vendorOrderId,
            'product_id'        => $this->productId,
            'amount'            => $this->amount,
            'commission_rate'   => $this->commissionRate,
            'commission_amount' => $this->commissionAmount,
            'vendor_amount'     => $this->vendorAmount,
            'status'            => $this->status,
            'paid_at'           => $this->paidAt,
            'created_at'        => $this->createdAt,
        ];
    }
}
