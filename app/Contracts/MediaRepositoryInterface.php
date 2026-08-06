<?php

declare(strict_types=1);

namespace VMP\Contracts;

defined('ABSPATH') || exit;

use VMP\DTO\MediaDTO;

interface MediaRepositoryInterface
{
    public function find(int $id): ?MediaDTO;
    public function findByVendor(int $vendorId, array $filters = []): array;
    public function findByAttachment(int $attachmentId): ?MediaDTO;
    public function create(MediaDTO $dto): MediaDTO;
    public function update(int $id, MediaDTO $dto): bool;
    public function delete(int $id): bool;
    public function deleteByVendor(int $vendorId): int;
    public function countByVendor(int $vendorId): int;
    public function paginate(int $vendorId, int $page = 1, int $perPage = 20): array;
}
