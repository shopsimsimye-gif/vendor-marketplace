<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * Class OrderDTO
 *
 * Description of administrative platform component OrderDTO.
 *
 * @package vendor-marketplace
 */
final class OrderDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly int $vendorId = 0,
        public readonly int $orderId = 0,
        public readonly int $parentOrderId = 0,
        public readonly string $status = 'pending',
        public readonly float $total = 0.0,
        public readonly float $commission = 0.0,
        public readonly float $vendorEarnings = 0.0,
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
            orderId: (int) ($data['order_id'] ?? 0),
            parentOrderId: (int) ($data['parent_order_id'] ?? 0),
            status: (string) ($data['status'] ?? 'pending'),
            total: (float) ($data['total'] ?? 0.0),
            commission: (float) ($data['commission'] ?? 0.0),
            vendorEarnings: (float) ($data['vendor_earnings'] ?? 0.0),
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
            'id'              => $this->id,
            'vendor_id'       => $this->vendorId,
            'order_id'        => $this->orderId,
            'parent_order_id' => $this->parentOrderId,
            'status'          => $this->status,
            'total'           => $this->total,
            'commission'      => $this->commission,
            'vendor_earnings' => $this->vendorEarnings,
            'created_at'      => $this->createdAt,
            'updated_at'      => $this->updatedAt,
        ];
    }
}
