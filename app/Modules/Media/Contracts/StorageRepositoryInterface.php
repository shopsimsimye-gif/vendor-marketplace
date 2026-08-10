<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Contracts;

defined('ABSPATH') || exit;

use VMP\Modules\Media\DTO\StorageConfigDTO;

interface StorageRepositoryInterface
{
    public function find(int $id): ?StorageConfigDTO;
    public function findByVendor(int $vendorId): ?StorageConfigDTO;
    public function findDefault(): ?StorageConfigDTO;
    public function create(StorageConfigDTO $dto): StorageConfigDTO;
    public function update(int $id, StorageConfigDTO $dto): bool;
    public function delete(int $id): bool;
    public function getAll(): array;
    public function getByType(string $type): array;
}
