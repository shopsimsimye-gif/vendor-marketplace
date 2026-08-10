<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Contracts;

defined('ABSPATH') || exit;

use VMP\Modules\Media\DTO\FolderDTO;

interface FolderRepositoryInterface
{
    public function find(int $id): ?FolderDTO;
    public function findByVendor(int $vendorId, int $parentId = 0): array;
    public function findByVendorFlat(int $vendorId): array;
    public function create(FolderDTO $dto): FolderDTO;
    public function update(int $id, FolderDTO $dto): bool;
    public function delete(int $id): bool;
    public function deleteByVendor(int $vendorId): int;
    public function countByVendor(int $vendorId): int;
    public function getTree(int $vendorId): array;
    public function getBreadcrumbs(int $folderId): array;
    public function move(int $id, int $newParentId): bool;
    public function hasChildren(int $id): bool;
    public function getFullPath(int $id): string;
}
