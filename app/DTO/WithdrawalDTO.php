<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * Class WithdrawalDTO
 *
 * Description of administrative platform component WithdrawalDTO.
 *
 * @package vendor-marketplace
 */
final class WithdrawalDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly int $vendorId = 0,
        public readonly float $amount = 0.0,
        public readonly string $status = 'pending',
        public readonly string $method = 'bank_transfer',
        public readonly array $methodDetails = [],
        public readonly string $notes = '',
        public readonly int $processedBy = 0,
        public readonly ?string $processedAt = null,
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
            amount: (float) ($data['amount'] ?? 0.0),
            status: (string) ($data['status'] ?? 'pending'),
            method: (string) ($data['method'] ?? 'bank_transfer'),
            methodDetails: is_string($data['method_details'] ?? null) 
                ? (json_decode($data['method_details'], true) ?: []) 
                : (is_array($data['method_details'] ?? null) ? $data['method_details'] : []),
            notes: (string) ($data['notes'] ?? ''),
            processedBy: (int) ($data['processed_by'] ?? 0),
            processedAt: $data['processed_at'] ?? null,
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
            'id'              => $this->id,
            'vendor_id'       => $this->vendorId,
            'amount'          => $this->amount,
            'status'          => $this->status,
            'method'          => $this->method,
            'method_details'  => $this->methodDetails,
            'notes'           => $this->notes,
            'processed_by'    => $this->processedBy,
            'processed_at'    => $this->processedAt,
            'created_at'      => $this->createdAt,
        ];
    }
}
