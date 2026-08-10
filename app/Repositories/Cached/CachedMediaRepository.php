<?php
namespace VMP\Repositories\Cached;

defined('ABSPATH') || exit;

use VMP\Contracts\MediaRepositoryInterface;
use VMP\Repositories\MediaRepository;
use VMP\Support\Cache\Manager as CacheManager;
use VMP\DTO\MediaDTO;

/**
 * CachedMediaRepository — Decorator Pattern لتخزين وسائط البائعين في الكاش.
 *
 * قراءات مُخزّنة (find/findByVendor/findByAttachment/countByVendor/paginate)؛
 * كل كتابة تمسح مجموعة الكاش بالكامل لضمان التزامن.
 */
class CachedMediaRepository implements MediaRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_media';

    public function __construct(
        private MediaRepository $repository
    ) {}

    public function find(int $id): ?MediaDTO
    {
        $key = 'media_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $media = $this->repository->find($id);
        if ($media) {
            CacheManager::set($key, $media, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $media;
    }

    public function findByVendor(int $vendorId, array $filters = []): array
    {
        $key = 'media_vendor_' . $vendorId . '_' . md5(serialize($filters));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->findByVendor($vendorId, $filters);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function findByAttachment(int $attachmentId): ?MediaDTO
    {
        $key = 'media_attachment_' . $attachmentId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $media = $this->repository->findByAttachment($attachmentId);
        if ($media) {
            CacheManager::set($key, $media, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $media;
    }

    public function create(MediaDTO $dto): MediaDTO
    {
        $media = $this->repository->create($dto);
        if ($media) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $media;
    }

    public function update(int $id, MediaDTO $dto): bool
    {
        $updated = $this->repository->update($id, $dto);
        if ($updated) {
            CacheManager::delete('media_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }

    public function delete(int $id): bool
    {
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            CacheManager::delete('media_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $deleted;
    }

    public function deleteByVendor(int $vendorId): int
    {
        $deleted = $this->repository->deleteByVendor($vendorId);
        if ($deleted) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $deleted;
    }

    public function countByVendor(int $vendorId): int
    {
        $key = 'media_count_' . $vendorId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (int) $cached;

        $count = $this->repository->countByVendor($vendorId);
        CacheManager::set($key, $count, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $count;
    }

    public function paginate(int $vendorId, int $page = 1, int $perPage = 20): array
    {
        $key = 'media_page_' . $vendorId . '_' . $page . '_' . $perPage;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $result = $this->repository->paginate($vendorId, $page, $perPage);
        CacheManager::set($key, $result, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $result;
    }
}
