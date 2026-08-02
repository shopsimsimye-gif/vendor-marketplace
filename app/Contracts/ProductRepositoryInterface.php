<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

/**
 * واجهة مستودع المنتجات
 */
interface ProductRepositoryInterface
{
    /**
     * ربط منتج ببائع
     */
    public function create(int $vendor_id, int $product_id, array $data = []): int|false;

    /**
     * البحث بواسطة المعرف الداخلي
     */
    public function find(int $id): ?object;

    /**
     * البحث بواسطة معرف المنتج (WooCommerce product_id)
     */
    public function findByProductId(int $product_id): ?object;

    /**
     * تحديث بيانات منتج
     */
    public function update(int $id, array $data): bool;

    /**
     * حذف منتج من مستودع البائع
     */
    public function delete(int $id): bool;

    /**
     * الموافقة على منتج
     */
    public function approve(int $id): bool;

    /**
     * رفض منتج
     */
    public function reject(int $id): bool;

    /**
     * جلب منتجات بائع معين
     */
    public function getByVendor(int $vendor_id, array $args = []): array;

    /**
     * عدد منتجات بائع معين
     */
    public function countByVendor(int $vendor_id, string $status = ''): int;

    /**
     * جلب المنتجات المعلقة للمراجعة
     */
    public function getPending(int $limit = 100): array;

    /**
     * جلب المنتجات المميزة لبائع
     */
    public function getFeatured(int $vendor_id, int $limit = 5): array;
}
