<?php

declare(strict_types=1);

namespace VMP\Modules\Media\DTO;

defined('ABSPATH') || exit;

use JsonSerializable;

class StorageConfigDTO implements JsonSerializable
{
    public ?int $id = null;
    public int $vendorId;
    public string $type; // local, s3, wasabi, r2, digitalocean, google_cloud, azure_blob
    public string $name;
    public array $config = []; // bucket, region, access_key, secret_key, endpoint, etc.
    public bool $isDefault = false;
    public bool $isActive = true;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public function __construct(
        int $vendorId,
        string $type,
        string $name,
        array $config,
        ?int $id = null,
        bool $isDefault = false,
        bool $isActive = true,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->vendorId = $vendorId;
        $this->type = $type;
        $this->name = $name;
        $this->config = $config;
        $this->isDefault = $isDefault;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            vendorId: (int) $row['vendor_id'],
            type: $row['type'],
            name: $row['name'],
            config: $row['config'] ? json_decode($row['config'], true) : [],
            isDefault: (bool) ($row['is_default'] ?? false),
            isActive: (bool) ($row['is_active'] ?? true),
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'vendor_id' => $this->vendorId,
            'type' => $this->type,
            'name' => $this->name,
            'config' => $this->config ? json_encode($this->config) : null,
            'is_default' => $this->isDefault,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ], fn($v) => $v !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
