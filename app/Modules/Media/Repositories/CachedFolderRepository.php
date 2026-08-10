<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Repositories;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Contracts\FolderRepositoryInterface;
use VMP\Modules\Media\DTO\FolderDTO;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedFolderRepository — Decorator لمجلدات وحدة Media.
 * قراءات مخزنة؛ كل كتابة تمسح المجموعة.
 */
class CachedFolderRepository implements FolderRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_folders';

    public function __construct(
        private FolderRepository $repository
    ) {}

    public function find(int $id): ?FolderDTO
    {
        $key = 'folder_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $folder = $this->repository->find($id);
        if ($folder) {
            CacheManager::set($key, $folder, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $folder;
    }

    public function findByVendor(int $vendorId, int $parentId = 0): array
    {
        $key = 'folders_vendor_' . $vendorId . '_parent_' . $parentId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->findByVendor($vendorId, $parentId);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function findByVendorFlat(int $vendorId): array
    {
        $key = 'folders_vendor_flat_' . $vendorId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->findByVendorFlat($vendorId);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function create(FolderDTO $dto): FolderDTO
    {
        $folder = $this->repository->create($dto);
        if ($folder) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $folder;
    }

    public function update(int $id, FolderDTO $dto): bool
    {
        $updated = $this->repository->update($id, $dto);
        if ($updated) {
            CacheManager::delete('folder_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }

    public function delete(int $id): bool
    {
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            CacheManager::delete('folder_' . $id, self::CACHE_GROUP);
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
        $key = 'folders_count_' . $vendorId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (int) $cached;

        $count = $this->repository->countByVendor($vendorId);
        CacheManager::set($key, $count, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $count;
    }

    public function getTree(int $vendorId): array
    {
        $key = 'folders_tree_' . $vendorId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $tree = $this->repository->getTree($vendorId);
        CacheManager::set($key, $tree, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $tree;
    }

    public function getBreadcrumbs(int $folderId): array
    {
        $key = 'folders_breadcrumbs_' . $folderId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getBreadcrumbs($folderId);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function move(int $id, int $newParentId): bool
    {
        $moved = $this->repository->move($id, $newParentId);
        if ($moved) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $moved;
    }

    public function hasChildren(int $id): bool
    {
        return $this->repository->hasChildren($id); // قراءة خفيفة؛ لا كاش
    }

    public function getFullPath(int $id): string
    {
        $key = 'folders_path_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return (string) $cached;

        $path = $this->repository->getFullPath($id);
        CacheManager::set($key, $path, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $path;
    }
}
