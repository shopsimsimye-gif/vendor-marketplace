<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * Class VendorDTO
 *
 * Description of administrative platform component VendorDTO.
 *
 * @package vendor-marketplace
 */
final class VendorDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly int $userId = 0,
        public readonly string $storeName = '',
        public readonly string $storeSlug = '',
        public readonly string $storeDescription = '',
        public readonly string $storeAddress = '',
        public readonly string $storePhone = '',
        public readonly string $storeEmail = '',
        public readonly int $storeLogo = 0,
        public readonly int $storeBanner = 0,
        public readonly string $whatsappNumber = '',
        public readonly string $whatsappMessage = '',
        public readonly string $customCss = '',
        public readonly string $status = 'pending',
        public readonly bool $isTrusted = false,
        public readonly float $balance = 0.0,
        public readonly string $subscriptionPlan = 'free',
        public readonly string $subscriptionStatus = 'active',
        public readonly ?string $subscriptionStart = null,
        public readonly ?string $subscriptionExpiry = null,
        public readonly string $adminNotes = '',
        public readonly float $rating = 0.0,
        public readonly int $reviewCount = 0,
        public readonly int $totalProducts = 0,
        public readonly int $totalOrders = 0,
        public readonly float $totalSales = 0.0
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
            userId: (int) ($data['user_id'] ?? 0),
            storeName: (string) ($data['store_name'] ?? ''),
            storeSlug: (string) ($data['store_slug'] ?? ''),
            storeDescription: (string) ($data['store_description'] ?? ''),
            storeAddress: (string) ($data['store_address'] ?? ''),
            storePhone: (string) ($data['store_phone'] ?? ''),
            storeEmail: (string) ($data['store_email'] ?? ''),
            storeLogo: (int) ($data['store_logo'] ?? 0),
            storeBanner: (int) ($data['store_banner'] ?? 0),
            whatsappNumber: (string) ($data['whatsapp_number'] ?? ''),
            whatsappMessage: (string) ($data['whatsapp_message'] ?? ''),
            customCss: (string) ($data['custom_css'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            isTrusted: !empty($data['is_trusted']),
            balance: (float) ($data['balance'] ?? 0.0),
            subscriptionPlan: (string) ($data['subscription_plan'] ?? 'free'),
            subscriptionStatus: (string) ($data['subscription_status'] ?? 'active'),
            subscriptionStart: $data['subscription_start'] ?? null,
            subscriptionExpiry: $data['subscription_expiry'] ?? null,
            adminNotes: (string) ($data['admin_notes'] ?? ''),
            rating: (float) ($data['rating'] ?? 0.0),
            reviewCount: (int) ($data['review_count'] ?? 0),
            totalProducts: (int) ($data['total_products'] ?? 0),
            totalOrders: (int) ($data['total_orders'] ?? 0),
            totalSales: (float) ($data['total_sales'] ?? 0.0)
        );
    }

    /**
     * Create DTO from object (stdClass) or array.
     * Backwards-compatible helper used across the codebase where repository returns objects.
     *
     * @param object|array $data
     * @return static
     */
    public static function fromObject(object|array $data): static
    {
        if (is_object($data)) {
            $data = (array) $data;
        }
        return self::fromArray(is_array($data) ? $data : []);
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'store_name' => $this->storeName,
            'store_slug' => $this->storeSlug,
            'store_description' => $this->storeDescription,
            'store_address' => $this->storeAddress,
            'store_phone' => $this->storePhone,
            'store_email' => $this->storeEmail,
            'store_logo' => $this->storeLogo,
            'store_banner' => $this->storeBanner,
            'whatsapp_number' => $this->whatsappNumber,
            'whatsapp_message' => $this->whatsappMessage,
            'custom_css' => $this->customCss,
            'status' => $this->status,
            'is_trusted' => $this->isTrusted,
            'balance' => $this->balance,
            'subscription_plan' => $this->subscriptionPlan,
            'subscription_status' => $this->subscriptionStatus,
            'subscription_start' => $this->subscriptionStart,
            'subscription_expiry' => $this->subscriptionExpiry,
            'admin_notes' => $this->adminNotes,
            'rating' => $this->rating,
            'review_count' => $this->reviewCount,
            'total_products' => $this->totalProducts,
            'total_orders' => $this->totalOrders,
            'total_sales' => $this->totalSales,
        ];
    }
}
