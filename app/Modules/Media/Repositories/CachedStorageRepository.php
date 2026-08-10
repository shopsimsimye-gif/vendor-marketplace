<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Repositories;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Contracts\StorageRepositoryInterface;
use VMP\Modules\Media\DTO\StorageConfigDTO;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * CachedStorageRepository — Decorator لإعدادات التخزين.
 * قراءات مخزنة؛ كل كتابة تمسح المجموعة.
 */
class CachedStorageRepository implements StorageRepositoryInterface
{
    private const CACHE_GROUP = 'vmp_storage';

    public function __construct(
        private StorageRepository $repository
    ) {}

    public function find(int $id): ?StorageConfigDTO
    {
        $key = 'storage_' . $id;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $storage = $this->repository->find($id);
        if ($storage) {
            CacheManager::set($key, $storage, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $storage;
    }

    public function findByVendor(int $vendorId): ?StorageConfigDTO
    {
        $key = 'storage_vendor_' . $vendorId;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $storage = $this->repository->findByVendor($vendorId);
        if ($storage) {
            CacheManager::set($key, $storage, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $storage;
    }

    public function findDefault(): ?StorageConfigDTO
    {
        $key = 'storage_default';
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $storage = $this->repository->findDefault();
        if ($storage) {
            CacheManager::set($key, $storage, CacheManager::configuredTtl(), self::CACHE_GROUP);
        }
        return $storage;
    }

    public function create(StorageConfigDTO $dto): StorageConfigDTO
    {
        $storage = $this->repository->create($dto);
        if ($storage) {
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $storage;
    }

    public function update(int $id, StorageConfigDTO $dto): bool
    {
        $updated = $this->repository->update($id, $dto);
        if ($updated) {
            CacheManager::delete('storage_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $updated;
    }

    public function delete(int $id): bool
    {
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            CacheManager::delete('storage_' . $id, self::CACHE_GROUP);
            CacheManager::flush(self::CACHE_GROUP);
        }
        return $deleted;
    }

    public function getAll(): array
    {
        $key = 'storage_all';
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getAll();
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }

    public function getByType(string $type): array
    {
        $key = 'storage_type_' . $type;
        $cached = CacheManager::get($key, self::CACHE_GROUP);
        if ($cached !== false) return $cached;

        $items = $this->repository->getByType($type);
        CacheManager::set($key, $items, CacheManager::configuredTtl(), self::CACHE_GROUP);
        return $items;
    }
}
