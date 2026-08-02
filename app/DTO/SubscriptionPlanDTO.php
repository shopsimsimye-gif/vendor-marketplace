<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * Class SubscriptionPlanDTO
 *
 * Description of administrative platform component SubscriptionPlanDTO.
 *
 * @package vendor-marketplace
 */
final class SubscriptionPlanDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly string $name = '',
        public readonly string $slug = '',
        public readonly string $description = '',
        public readonly float $price = 0.0,
        public readonly string $billingPeriod = 'month',
        public readonly int $billingInterval = 1,
        public readonly int $maxProducts = 10,
        public readonly float $commissionRate = 10.0,
        public readonly array $features = [],
        public readonly bool $isActive = true,
        public readonly int $sortOrder = 0,
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
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            price: (float) ($data['price'] ?? 0.0),
            billingPeriod: (string) ($data['billing_period'] ?? 'month'),
            billingInterval: (int) ($data['billing_interval'] ?? 1),
            maxProducts: (int) ($data['max_products'] ?? 10),
            commissionRate: (float) ($data['commission_rate'] ?? 10.0),
            features: is_string($data['features'] ?? null)
                ? (json_decode($data['features'], true) ?: [])
                : (is_array($data['features'] ?? null) ? $data['features'] : []),
            isActive: (bool) ($data['is_active'] ?? true),
            sortOrder: (int) ($data['sort_order'] ?? 0),
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
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'description'      => $this->description,
            'price'            => $this->price,
            'billing_period'   => $this->billingPeriod,
            'billing_interval' => $this->billingInterval,
            'max_products'     => $this->maxProducts,
            'commission_rate'  => $this->commissionRate,
            'features'         => $this->features,
            'is_active'        => $this->isActive,
            'sort_order'       => $this->sortOrder,
            'created_at'       => $this->createdAt,
        ];
    }
}
