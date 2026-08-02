<?php
namespace VMP\Events\Product;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * يُطلق عند إضافة منتج جديد من قِبَل بائع
 */
class ProductCreated extends AbstractEvent
{
    public function __construct(
        public readonly int    $productId,
        public readonly int    $vendorId,
        public readonly int    $wcProductId,
        public readonly string $productName
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
        return 'product.created';
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'product_id'    => $this->productId,
            'vendor_id'     => $this->vendorId,
            'wc_product_id' => $this->wcProductId,
            'product_name'  => $this->productName,
        ]);
    }
}
