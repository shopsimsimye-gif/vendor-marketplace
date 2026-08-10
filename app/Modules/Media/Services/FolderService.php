<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Services;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Contracts\FolderRepositoryInterface;
use VMP\Modules\Media\Contracts\MediaRepositoryInterface;
use VMP\Modules\Media\DTO\FolderDTO;
use VMP\Core\EventManager;
use VMP\Core\Logger;

class FolderService
{
    public function __construct(
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly EventManager $eventManager,
        private readonly Logger $logger,
    ) {}

    public function create(int $vendorId, string $name, ?int $parentId = null): FolderDTO
    {
        // Build path
        $path = $name;
        if ($parentId > 0) {
            $parent = $this->folderRepository->find($parentId);
            if ($parent && $parent->vendorId === $vendorId) {
                $path = $parent->path . '/' . $name;
            }
        }

        // Check for duplicate slug in same parent
        $siblings = $this->folderRepository->findByVendor($vendorId, $parentId);
        $slug = sanitize_title($name);
        $originalSlug = $slug;
        $counter = 1;
        while (in_array($slug, array_column(array_map(fn($f) => $f->toArray(), $siblings), 'slug'), true)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Get max sort order
        $maxSort = 0;
        foreach ($siblings as $sibling) {
            $maxSort = max($maxSort, $sibling->sortOrder);
        }

        $dto = new FolderDTO(
            vendorId: $vendorId,
            name: $name,
            parentId: $parentId,
            sortOrder: $maxSort + 1,
        );
        $dto->slug = $slug;
        $dto->path = $path;

        $folder = $this->folderRepository->create($dto);

        $this->eventManager->trigger('vmp_folder_created', $folder);
        $this->logger->info('Folder created', ['folder_id' => $folder->id, 'vendor_id' => $vendorId]);

        return $folder;
    }

    public function update(int $folderId, int $vendorId, array $data): ?FolderDTO
    {
        $folder = $this->folderRepository->find($folderId);
        if (!$folder || $folder->vendorId !== $vendorId) {
            return null;
        }

        if (isset($data['name'])) {
            $folder->name = $data['name'];
            $folder->slug = sanitize_title($data['name']);
        }
        if (isset($data['parent_id'])) {
            $folder->parentId = (int) $data['parent_id'];
        }
        if (isset($data['sort_order'])) {
            $folder->sortOrder = (int) $data['sort_order'];
        }

        // Update path if parent changed
        if (isset($data['parent_id'])) {
            $newPath = $folder->name;
            if ($folder->parentId > 0) {
                $parent = $this->folderRepository->find($folder->parentId);
                if ($parent) {
                    $newPath = $parent->path . '/' . $folder->name;
                }
            }
            $folder->path = $newPath;
        }

        $this->folderRepository->update($folderId, $folder);

        $this->eventManager->trigger('vmp_folder_updated', $folder);
        $this->logger->info('Folder updated', ['folder_id' => $folderId, 'vendor_id' => $vendorId]);

        return $this->folderRepository->find($folderId);
    }

    public function delete(int $folderId, int $vendorId): bool
    {
        $folder = $this->folderRepository->find($folderId);
        if (!$folder || $folder->vendorId !== $vendorId) {
            return false;
        }

        // Check if folder has children
        if ($this->folderRepository->hasChildren($folderId)) {
            throw new \RuntimeException(__('Cannot delete folder with subfolders', 'vendor-marketplace'));
        }

        // Check if folder has media
        $mediaCount = $this->mediaRepository->countByFolder($folderId);
        if ($mediaCount > 0) {
            throw new \RuntimeException(__('Cannot delete folder with media files', 'vendor-marketplace'));
        }

        $deleted = $this->folderRepository->delete($folderId);

        if ($deleted) {
            $this->eventManager->trigger('vmp_folder_deleted', $folder);
            $this->logger->info('Folder deleted', ['folder_id' => $folderId, 'vendor_id' => $vendorId]);
        }

        return $deleted;
    }

    public function getTree(int $vendorId): array
    {
        return $this->folderRepository->getTree($vendorId);
    }

    public function getFlat(int $vendorId): array
    {
        return $this->folderRepository->findByVendorFlat($vendorId);
    }

    public function getBreadcrumbs(int $folderId): array
    {
        return $this->folderRepository->getBreadcrumbs($folderId);
    }

    public function move(int $folderId, int $vendorId, int $newParentId): bool
    {
        $folder = $this->folderRepository->find($folderId);
        if (!$folder || $folder->vendorId !== $vendorId) {
            return false;
        }

        if ($newParentId > 0) {
            $newParent = $this->folderRepository->find($newParentId);
            if (!$newParent || $newParent->vendorId !== $vendorId) {
                return false;
            }
        }

        return $this->folderRepository->move($folderId, $newParentId);
    }

    public function count(int $vendorId): int
    {
        return $this->folderRepository->countByVendor($vendorId);
    }
}
