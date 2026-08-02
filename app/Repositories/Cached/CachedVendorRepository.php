<?php
namespace VMP\Repositories\Cached;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Repositories\VendorRepository;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedVendorRepository — Decorator Pattern لتخزين البائعين في الكاش
 */
class CachedVendorRepository implements VendorRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_vendors';

    public function __construct(
        private VendorRepository $repository
    ) {}

    /**
     * Create functionality helper.
     *
     * @param array $data Description index.
     * @return int|false Output payload.
     */
    public function create(array $data): int|false
    {
        $id = $this->repository->create($data);
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
        $key = 'vendor_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $vendor = $this->repository->find($id);
        if ($vendor) {
            CacheManager::set($key, $vendor, 3600, self::CACHE_GROUP);
        }
        return $vendor;
    }

    /**
     * FindByUserId functionality helper.
     *
     * @param int $user_id Description index.
     * @return ?object Output payload.
     */
    public function findByUserId(int $user_id): ?object
    {
        $key = 'vendor_user_' . $user_id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $vendor = $this->repository->findByUserId($user_id);
        if ($vendor) {
            CacheManager::set($key, $vendor, 3600, self::CACHE_GROUP);
        }
        return $vendor;
    }

    /**
     * FindBySlug functionality helper.
     *
     * @param string $slug Description index.
     * @return ?object Output payload.
     */
    public function findBySlug(string $slug): ?object
    {
        $key = 'vendor_slug_' . md5($slug);
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $vendor = $this->repository->findBySlug($slug);
        if ($vendor) {
            CacheManager::set($key, $vendor, 3600, self::CACHE_GROUP);
        }
        return $vendor;
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
            CacheManager::delete('vendor_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP); // مسح قوائم البائعين
        }
        return $updated;
    }

    /**
     * SlugExists functionality helper.
     *
     * @param string $slug Description index.
     * @return bool Output payload.
     */
    public function slugExists(string $slug): bool
    {
        return $this->repository->slugExists($slug); // لا داعي للكاش هنا للتأكد بدقة
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
            CacheManager::delete('vendor_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $approved;
    }

    /**
     * Reject functionality helper.
     *
     * @param int $id Description index.
     * @param string $reason Description index.
     * @return bool Output payload.
     */
    public function reject(int $id, string $reason = ''): bool
    {
        $rejected = $this->repository->reject($id, $reason);
        if ($rejected) {
            CacheManager::delete('vendor_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $rejected;
    }

    /**
     * GetAll functionality helper.
     *
     * @param array $args Description index.
     * @return array Output payload.
     */
    public function getAll(array $args = []): array
    {
        $key = 'vendors_all_' . md5(serialize($args));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $vendors = $this->repository->getAll($args);
        CacheManager::set($key, $vendors, 600, self::CACHE_GROUP);
        return $vendors;
    }

    /**
     * UpdateBalance functionality helper.
     *
     * @param int $id Description index.
     * @param float $amount Description index.
     * @return bool Output payload.
     */
    public function updateBalance(int $id, float $amount): bool
    {
        $updated = $this->repository->updateBalance($id, $amount);
        if ($updated) {
            CacheManager::delete('vendor_' . $id, self::CACHE_GROUP);
        }
        return $updated;
    }

    /**
     * GetCount functionality helper.
     *
     * @param string $status Description index.
     * @return int Output payload.
     */
    public function getCount(string $status = ''): int
    {
        $key = 'vendors_count_' . $status;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (int) $cached;

        $count = $this->repository->getCount($status);
        CacheManager::set($key, $count, 600, self::CACHE_GROUP);
        return $count;
    }

    /**
     * GetLatestPending functionality helper.
     *
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getLatestPending(int $limit = 5): array
    {
        $key = 'vendors_latest_pending_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $vendors = $this->repository->getLatestPending($limit);
        CacheManager::set($key, $vendors, 600, self::CACHE_GROUP);
        return $vendors;
    }

    /**
     * GetActiveVendors functionality helper.
     *
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getActiveVendors(int $limit = 50): array
    {
        $key = 'vendors_active_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $vendors = $this->repository->getActiveVendors($limit);
        CacheManager::set($key, $vendors, 3600, self::CACHE_GROUP);
        return $vendors;
    }

    /**
     * Search functionality helper.
     *
     * @param string $query Description index.
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function search(string $query, int $limit = 20): array
    {
        $key = 'vendors_search_' . md5($query) . '_' . $limit;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $vendors = $this->repository->search($query, $limit);
        CacheManager::set($key, $vendors, 300, self::CACHE_GROUP);
        return $vendors;
    }

    /**
     * GetQuickStats functionality helper.
     *
     * @return array Output payload.
     */
    public function getQuickStats(): array
    {
        $key = 'vendors_quick_stats';
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $stats = $this->repository->getQuickStats();
        CacheManager::set($key, $stats, 600, self::CACHE_GROUP);
        return $stats;
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
            CacheManager::delete('vendor_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $deleted;
    }

    /**
     * الحصول على إحصاءات تقييمات البائع
     *
     * لا يُستخدم الكاش الخارجي هنا لأن التقييمات تتغير بشكل متكرر؛
     * نفوّض مباشرةً للـ Repository الداخلي الذي يحمل كاشه الخاص.
     *
     * @note طبقتا الكاش:
     *   1. CacheManager (هذا الكلاس) — كاش خارجي لقوائم البائعين وبياناتهم الثابتة.
     *   2. wp_cache_* في VendorRepository — كاش داخلي لطلبات نفس الجلسة.
     *
     * @param int $vendorId معرف البائع
     * @return array{count: int, avg_rating: float}
     */
    public function getReviewStats(int $vendorId): array
    {
        return $this->repository->getReviewStats($vendorId);
    }
}
