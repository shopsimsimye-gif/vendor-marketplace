<?php
namespace VMP\Repositories\Cached;

defined('ABSPATH') || exit;

use VMP\Contracts\OrderRepositoryInterface;
use VMP\Repositories\OrderRepository;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedOrderRepository — Decorator لطلبات البائعين.
 * قراءات مخزنة؛ كل كتابة تمسح مجموعة الطلبات.
 */
class CachedOrderRepository implements OrderRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_orders';

    public function __construct(
        private OrderRepository $repository
    ) {}

    public function create(array $data): int|false
    {
        $id = $this->repository->create($data);
        if ($id) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $id;
    }

    public function find(int $id): ?object
    {
        $key = 'order_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $order = $this->repository->find($id);
        if ($order) {
            CacheManager::set($key, $order, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $order;
    }

    public function findByOrderId(int $order_id, int $vendor_id): ?object
    {
        $key = 'order_wc_' . $order_id . '_vendor_' . $vendor_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $order = $this->repository->findByOrderId($order_id, $vendor_id);
        if ($order) {
            CacheManager::set($key, $order, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $order;
    }

    public function update(int $id, array $data): bool
    {
        $updated = $this->repository->update($id, $data);
        if ($updated) {
            CacheManager::delete('order_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }

    public function getByVendor(int $vendor_id, array $args = []): array
    {
        $key = 'orders_vendor_' . $vendor_id . '_' . md5(serialize($args));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getByVendor($vendor_id, $args);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function getByParentOrder(int $parent_order_id): array
    {
        $key = 'orders_parent_' . $parent_order_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getByParentOrder($parent_order_id);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function countByVendor(int $vendor_id, string $status = ''): int
    {
        $key = 'orders_count_' . $vendor_id . '_' . $status;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (int) $cached;

        $count = $this->repository->countByVendor($vendor_id, $status);
        CacheManager::set($key, $count, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $count;
    }

    public function getTotalSales(int $vendor_id): float
    {
        $key = 'orders_sales_' . $vendor_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (float) $cached;

        $total = $this->repository->getTotalSales($vendor_id);
        CacheManager::set($key, $total, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $total;
    }

    public function getTotalEarnings(int $vendor_id): float
    {
        $key = 'orders_earnings_' . $vendor_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (float) $cached;

        $total = $this->repository->getTotalEarnings($vendor_id);
        CacheManager::set($key, $total, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $total;
    }

    public function getTotalSalesForAllVendors(): float
    {
        $key = 'orders_sales_all';
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (float) $cached;

        $total = $this->repository->getTotalSalesForAllVendors();
        CacheManager::set($key, $total, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $total;
    }

    public function getTotalEarningsForAllVendors(): float
    {
        $key = 'orders_earnings_all';
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (float) $cached;

        $total = $this->repository->getTotalEarningsForAllVendors();
        CacheManager::set($key, $total, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $total;
    }

    public function getTotalEarningsByVendor(int $vendor_id): float
    {
        $key = 'orders_earnings_by_vendor_' . $vendor_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (float) $cached;

        $total = $this->repository->getTotalEarningsByVendor($vendor_id);
        CacheManager::set($key, $total, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $total;
    }
}
