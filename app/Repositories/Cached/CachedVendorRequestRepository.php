<?php
namespace VMP\Repositories\Cached;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRequestRepositoryInterface;
use VMP\Repositories\VendorRequestRepository;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedVendorRequestRepository — Decorator لطلبات انضمام البائعين.
 * قراءات مخزنة؛ كل كتابة تمسح مجموعة الطلبات.
 */
class CachedVendorRequestRepository implements VendorRequestRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_vendor_requests';

    public function __construct(
        private VendorRequestRepository $repository
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
        $key = 'vr_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $row = $this->repository->find($id);
        if ($row) {
            CacheManager::set($key, $row, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $row;
    }

    public function findByUserId(int $user_id): ?object
    {
        $key = 'vr_user_' . $user_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $row = $this->repository->findByUserId($user_id);
        if ($row) {
            CacheManager::set($key, $row, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $row;
    }

    public function findBySlug(string $slug): ?object
    {
        $key = 'vr_slug_' . md5($slug);
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $row = $this->repository->findBySlug($slug);
        if ($row) {
            CacheManager::set($key, $row, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $row;
    }

    public function update(int $id, array $data): bool
    {
        $updated = $this->repository->update($id, $data);
        if ($updated) {
            CacheManager::delete('vr_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }

    public function slugExists(string $slug): bool
    {
        // استدعاء مباشر — الدوال المركبة (check_store_slug) تحتاج أحدث قيمة
        return $this->repository->slugExists($slug);
    }

    public function approve(int $id, int $admin_id): int|false|\WP_Error
    {
        $result = $this->repository->approve($id, $admin_id);
        if (is_int($result) && $result > 0) {
            CacheManager::delete('vr_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $result;
    }

    public function reject(int $id, string $reason, int $admin_id): bool
    {
        $result = $this->repository->reject($id, $reason, $admin_id);
        if ($result) {
            CacheManager::delete('vr_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $result;
    }

    public function getAll(array $args = []): array
    {
        $key = 'vr_all_' . md5(serialize($args));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getAll($args);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function getCount(string $status = ''): int
    {
        $key = 'vr_count_' . $status;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (int) $cached;

        $count = $this->repository->getCount($status);
        CacheManager::set($key, $count, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $count;
    }

    public function getLatestPending(int $limit = 5): array
    {
        $key = 'vr_latest_pending_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getLatestPending($limit);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function delete(int $id): bool
    {
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            CacheManager::delete('vr_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $deleted;
    }

    public function search(string $query, int $limit = 20): array
    {
        $key = 'vr_search_' . md5($query) . '_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->search($query, $limit);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function getQuickStats(): array
    {
        $key = 'vr_quickstats';
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $stats = $this->repository->getQuickStats();
        CacheManager::set($key, $stats, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $stats;
    }
}
