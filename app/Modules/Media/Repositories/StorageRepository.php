<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Repositories;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Contracts\StorageRepositoryInterface;
use VMP\Modules\Media\DTO\StorageConfigDTO;

class StorageRepository implements StorageRepositoryInterface
{
    protected string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vmp_media_storage';
    }

    public function find(int $id): ?StorageConfigDTO
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ? StorageConfigDTO::fromArray($row) : null;
    }

    public function findByVendor(int $vendorId): ?StorageConfigDTO
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE vendor_id = %d AND is_active = 1 ORDER BY is_default DESC LIMIT 1", $vendorId),
            ARRAY_A
        );
        return $row ? StorageConfigDTO::fromArray($row) : null;
    }

    public function findDefault(): ?StorageConfigDTO
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE is_default = 1 AND is_active = 1 LIMIT 1"),
            ARRAY_A
        );
        return $row ? StorageConfigDTO::fromArray($row) : null;
    }

    public function create(StorageConfigDTO $dto): StorageConfigDTO
    {
        global $wpdb;
        $data = array_filter([
            'vendor_id' => $dto->vendorId,
            'type' => $dto->type,
            'name' => $dto->name,
            'config' => $dto->config ? json_encode($dto->config) : null,
            'is_default' => $dto->isDefault,
            'is_active' => $dto->isActive,
        ], fn($v) => $v !== null);

        // If this is default, unset others for the same vendor
        if ($dto->isDefault) {
            $wpdb->update($this->table, ['is_default' => 0], ['is_default' => 1, 'vendor_id' => $dto->vendorId], ['%d'], ['%d', '%d']);
        }

        $wpdb->insert($this->table, $data);
        return $this->find((int) $wpdb->insert_id);
    }

    public function update(int $id, StorageConfigDTO $dto): bool
    {
        global $wpdb;
        $data = array_filter([
            'type' => $dto->type,
            'name' => $dto->name,
            'config' => $dto->config ? json_encode($dto->config) : null,
            'is_default' => $dto->isDefault,
            'is_active' => $dto->isActive,
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return false;
        }

        // If this is default, unset others for the same vendor (excluding current record)
        if ($dto->isDefault) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table} SET is_default = 0 WHERE is_default = 1 AND id != %d AND vendor_id = %d",
                $id,
                $dto->vendorId
            ));
        }

        return (bool) $wpdb->update($this->table, $data, ['id' => $id], ['%s', '%d', '%s', '%d', '%d'], ['%d']);
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($this->table, ['id' => $id], ['%d']);
    }

    public function getAll(): array
    {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY is_default DESC, created_at ASC",
            ARRAY_A
        );
        return array_map([StorageConfigDTO::class, 'fromArray'], $results);
    }

    public function getByType(string $type): array
    {
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE type = %s AND is_active = 1 ORDER BY is_default DESC", $type),
            ARRAY_A
        );
        return array_map([StorageConfigDTO::class, 'fromArray'], $results);
    }
}
