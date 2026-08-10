<?php
namespace VMP\Repositories\Cached;

defined('ABSPATH') || exit;

use VMP\Contracts\WithdrawalRepositoryInterface;
use VMP\Repositories\WithdrawalRepository;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedWithdrawalRepository — Decorator لطلبات السحب.
 * قراءات مخزنة؛ كل كتابة تمسح مجموعة السحوبات.
 */
class CachedWithdrawalRepository implements WithdrawalRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_withdrawals';

    public function __construct(
        private WithdrawalRepository $repository
    ) {}

    public function create(array $data): int|false|\WP_Error
    {
        $id = $this->repository->create($data);
        if (is_int($id) && $id > 0) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $id;
    }

    public function find(int $id): ?object
    {
        $key = 'withdrawal_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $withdrawal = $this->repository->find($id);
        if ($withdrawal) {
            CacheManager::set($key, $withdrawal, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $withdrawal;
    }

    public function update(int $id, array $data): bool
    {
        $updated = $this->repository->update($id, $data);
        if ($updated) {
            CacheManager::delete('withdrawal_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }

    public function getByVendor(int $vendor_id, array $args = []): array
    {
        $key = 'withdrawals_vendor_' . $vendor_id . '_' . md5(serialize($args));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getByVendor($vendor_id, $args);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function countByVendor(int $vendor_id): int
    {
        $key = 'withdrawals_count_' . $vendor_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (int) $cached;

        $count = $this->repository->countByVendor($vendor_id);
        CacheManager::set($key, $count, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $count;
    }

    public function getPending(int $limit = 100): array
    {
        $key = 'withdrawals_pending_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getPending($limit);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function approve(int $id, int $processed_by): bool|\WP_Error
    {
        $result = $this->repository->approve($id, $processed_by);
        if ($result === true) {
            CacheManager::delete('withdrawal_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $result;
    }

    public function reject(int $id, int $processed_by, string $reason = ''): bool
    {
        $result = $this->repository->reject($id, $processed_by, $reason);
        if ($result) {
            CacheManager::delete('withdrawal_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $result;
    }
}
