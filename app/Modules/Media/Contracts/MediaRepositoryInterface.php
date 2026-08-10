<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Contracts;

defined('ABSPATH') || exit;

use VMP\Modules\Media\DTO\MediaDTO;

interface MediaRepositoryInterface
{
    public function find(int $id): ?MediaDTO;
    public function findByVendor(int $vendorId, array $filters = []): array;
    public function findByAttachment(int $attachmentId): ?MediaDTO;
    public function findByFolder(int $folderId, int $vendorId, array $filters = []): array;
    public function create(MediaDTO $dto): MediaDTO;
    public function update(int $id, MediaDTO $dto): bool;
    public function delete(int $id): bool;
    public function deleteByVendor(int $vendorId): int;
    public function countByVendor(int $vendorId): int;
    public function countByFolder(int $folderId): int;
    public function paginate(int $vendorId, int $page = 1, int $perPage = 20, array $filters = []): array;
    public function paginateByFolder(int $folderId, int $vendorId, int $page = 1, int $perPage = 20): array;
    public function moveToFolder(int $mediaId, int $folderId): bool;
    public function updateMetadata(int $id, array $metadata): bool;
}
