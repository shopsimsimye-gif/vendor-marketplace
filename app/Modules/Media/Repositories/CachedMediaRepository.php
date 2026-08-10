<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Repositories;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Contracts\MediaRepositoryInterface;
use VMP\Modules\Media\DTO\MediaDTO;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedMediaRepository — Decorator لوسائط وحدة Media.
 * قراءات مخزنة؛ كل كتابة تمسح المجموعة.
 */
class CachedMediaRepository implements MediaRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_media_module';

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

    public function findByFolder(int $folderId, int $vendorId, array $filters = []): array
    {
        $key = 'media_folder_' . $folderId . '_' . $vendorId . '_' . md5(serialize($filters));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->findByFolder($folderId, $vendorId, $filters);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
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

    public function countByFolder(int $folderId): int
    {
        $key = 'media_folder_count_' . $folderId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (int) $cached;

        $count = $this->repository->countByFolder($folderId);
        CacheManager::set($key, $count, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $count;
    }

    public function paginate(int $vendorId, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $key = 'media_page_' . $vendorId . '_' . $page . '_' . $perPage . '_' . md5(serialize($filters));
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $result = $this->repository->paginate($vendorId, $page, $perPage, $filters);
        CacheManager::set($key, $result, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $result;
    }

    public function paginateByFolder(int $folderId, int $vendorId, int $page = 1, int $perPage = 20): array
    {
        $key = 'media_folder_page_' . $folderId . '_' . $vendorId . '_' . $page . '_' . $perPage;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $result = $this->repository->paginateByFolder($folderId, $vendorId, $page, $perPage);
        CacheManager::set($key, $result, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $result;
    }

    public function moveToFolder(int $mediaId, int $folderId): bool
    {
        $moved = $this->repository->moveToFolder($mediaId, $folderId);
        if ($moved) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $moved;
    }

    public function updateMetadata(int $id, array $metadata): bool
    {
        $updated = $this->repository->updateMetadata($id, $metadata);
        if ($updated) {
            CacheManager::delete('media_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }
}
