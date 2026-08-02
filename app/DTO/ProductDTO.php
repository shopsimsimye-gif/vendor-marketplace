<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * Class ProductDTO
 *
 * Description of administrative platform component ProductDTO.
 *
 * @package vendor-marketplace
 */
final class ProductDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly int $vendorId = 0,
        public readonly int $productId = 0,
        public readonly string $status = 'pending',
        public readonly ?string $adminNotes = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly string $title = '',
        public readonly string $description = '',
        public readonly string $shortDescription = '',
        public readonly float $regularPrice = 0.0,
        public readonly float $salePrice = 0.0,
        public readonly int $stockQuantity = 0,
        public readonly string $stockStatus = 'instock',
        public readonly string $sku = '',
        public readonly array $categoryIds = [],
        public readonly array $tagIds = [],
        public readonly int $imageId = 0,
        public readonly array $galleryImageIds = []
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
            productId: (int) ($data['product_id'] ?? 0),
            status: (string) ($data['status'] ?? 'pending'),
            adminNotes: $data['admin_notes'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            title: (string) ($data['title'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            shortDescription: (string) ($data['short_description'] ?? ''),
            regularPrice: (float) ($data['regular_price'] ?? 0.0),
            salePrice: (float) ($data['sale_price'] ?? 0.0),
            stockQuantity: (int) ($data['stock_quantity'] ?? 0),
            stockStatus: (string) ($data['stock_status'] ?? 'instock'),
            sku: (string) ($data['sku'] ?? ''),
            categoryIds: is_array($data['category_ids'] ?? null) ? $data['category_ids'] : [],
            tagIds: is_array($data['tag_ids'] ?? null) ? $data['tag_ids'] : [],
            imageId: (int) ($data['image_id'] ?? 0),
            galleryImageIds: is_array($data['gallery_image_ids'] ?? null) ? $data['gallery_image_ids'] : []
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
            'id' => $this->id,
            'vendor_id' => $this->vendorId,
            'product_id' => $this->productId,
            'status' => $this->status,
            'admin_notes' => $this->adminNotes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'title' => $this->title,
            'description' => $this->description,
            'short_description' => $this->shortDescription,
            'regular_price' => $this->regularPrice,
            'sale_price' => $this->salePrice,
            'stock_quantity' => $this->stockQuantity,
            'stock_status' => $this->stockStatus,
            'sku' => $this->sku,
            'category_ids' => $this->categoryIds,
            'tag_ids' => $this->tagIds,
            'image_id' => $this->imageId,
            'gallery_image_ids' => $this->galleryImageIds,
        ];
    }
}
