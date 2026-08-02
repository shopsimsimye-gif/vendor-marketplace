<?php
namespace VMP\Repositories\Cached;

defined('ABSPATH') || exit;

use VMP\Contracts\ProductRepositoryInterface;
use VMP\Repositories\ProductRepository;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedProductRepository — Decorator Pattern لتخزين المنتجات في الكاش
 */
class CachedProductRepository implements ProductRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_products';

    public function __construct(
        private ProductRepository $repository
    ) {}

    /**
     * Create functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $product_id Description index.
     * @param array $data Description index.
     * @return int|false Output payload.
     */
    public function create(int $vendor_id, int $product_id, array $data = []): int|false
    {
        $id = $this->repository->create($vendor_id, $product_id, $data);
        if ($id) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $id;
    }

    /**
     * Find functionality helper.
     *
     * @param int $id Description index.
     * @return ?object Output payload.
     */
    public function find(int $id): ?object
    {
        $key = 'product_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $product = $this->repository->find($id);
        if ($product) {
            CacheManager::set($key, $product, 3600, self::CACHE_GROUP);
        }
        return $product;
    }

    /**
     * FindByProductId functionality helper.
     *
     * @param int $product_id Description index.
     * @return ?object Output payload.
     */
    public function findByProductId(int $product_id): ?object
    {
        $key = 'product_wc_' . $product_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $product = $this->repository->findByProductId($product_id);
        if ($product) {
            CacheManager::set($key, $product, 3600, self::CACHE_GROUP);
        }
        return $product;
    }

    /**
     * Update functionality helper.
     *
     * @param int $id Description index.
     * @param array $data Description index.
     * @return bool Output payload.
     */
    public function update(int $id, array $data): bool
    {
        $updated = $this->repository->update($id, $data);
        if ($updated) {
            CacheManager::delete('product_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }

    /**
     * Delete functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function delete(int $id): bool
    {
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            CacheManager::delete('product_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $deleted;
    }

    /**
     * Approve functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function approve(int $id): bool
    {
        $approved = $this->repository->approve($id);
        if ($approved) {
            CacheManager::delete('product_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $approved;
    }

    /**
     * Reject functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function reject(int $id): bool
    {
        $rejected = $this->repository->reject($id);
        if ($rejected) {
            CacheManager::delete('product_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $rejected;
    }

    /**
     * GetByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param array $args Description index.
     * @return array Output payload.
     */
    public function getByVendor(int $vendor_id, array $args = []): array
    {
        $key = 'products_vendor_' . $vendor_id . '_' . md5(serialize($args));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $products = $this->repository->getByVendor($vendor_id, $args);
        CacheManager::set($key, $products, 600, self::CACHE_GROUP);
        return $products;
    }

    /**
     * CountByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param string $status Description index.
     * @return int Output payload.
     */
    public function countByVendor(int $vendor_id, string $status = ''): int
    {
        $key = 'products_count_' . $vendor_id . '_' . $status;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (int) $cached;

        $count = $this->repository->countByVendor($vendor_id, $status);
        CacheManager::set($key, $count, 600, self::CACHE_GROUP);
        return $count;
    }

    /**
     * GetPending functionality helper.
     *
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getPending(int $limit = 100): array
    {
        $key = 'products_pending_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $products = $this->repository->getPending($limit);
        CacheManager::set($key, $products, 600, self::CACHE_GROUP);
        return $products;
    }

    /**
     * GetFeatured functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getFeatured(int $vendor_id, int $limit = 5): array
    {
        $key = 'products_featured_' . $vendor_id . '_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $products = $this->repository->getFeatured($vendor_id, $limit);
        CacheManager::set($key, $products, 3600, self::CACHE_GROUP);
        return $products;
    }
}
