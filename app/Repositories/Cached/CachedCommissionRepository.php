<?php
namespace VMP\Repositories\Cached;

defined('ABSPATH') || exit;

use VMP\Contracts\CommissionRepositoryInterface;
use VMP\Repositories\CommissionRepository;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedCommissionRepository — Decorator للعمولات.
 * قراءات مخزنة؛ كل كتابة تمسح مجموعة العمولات.
 */
class CachedCommissionRepository implements CommissionRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_commissions';

    public function __construct(
        private CommissionRepository $repository
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
        $key = 'commission_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $row = $this->repository->find($id);
        if ($row) {
            CacheManager::set($key, $row, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $row;
    }

    public function update(int $id, array $data): bool
    {
        $updated = $this->repository->update($id, $data);
        if ($updated) {
            CacheManager::delete('commission_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }

    public function markAsPaid(int $id): bool|\WP_Error
    {
        $result = $this->repository->markAsPaid($id);
        if ($result === true) {
            CacheManager::delete('commission_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $result;
    }

    public function markBulkAsPaid(array $ids): int
    {
        $count = $this->repository->markBulkAsPaid($ids);
        if ($count > 0) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $count;
    }

    public function getByVendor(int $vendor_id, array $args = []): array
    {
        $key = 'commissions_vendor_' . $vendor_id . '_' . md5(serialize($args));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getByVendor($vendor_id, $args);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function getAllPending(int $limit = 100): array
    {
        $key = 'commissions_pending_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getAllPending($limit);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function getAdminStats(): array
    {
        $key = 'commissions_admin_stats';
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $stats = $this->repository->getAdminStats();
        CacheManager::set($key, $stats, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $stats;
    }

    public function getTotalCommissions(string $status = ''): float
    {
        $key = 'commissions_total_' . $status;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (float) $cached;

        $total = $this->repository->getTotalCommissions($status);
        CacheManager::set($key, $total, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $total;
    }

    public function getTotalByVendorAndPeriod(int $vendor_id, string $date_from, string $date_to): array
    {
        $key = 'commissions_vendor_period_' . $vendor_id . '_' . md5($date_from . $date_to);
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getTotalByVendorAndPeriod($vendor_id, $date_from, $date_to);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function getMonthlyStats(int $vendor_id, int $months = 6): array
    {
        $key = 'commissions_monthly_' . $vendor_id . '_' . $months;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $stats = $this->repository->getMonthlyStats($vendor_id, $months);
        CacheManager::set($key, $stats, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $stats;
    }
}
