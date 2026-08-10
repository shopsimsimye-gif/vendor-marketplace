<?php

declare(strict_types=1);

namespace VMP\Modules\Media\DTO;

defined('ABSPATH') || exit;

use JsonSerializable;

class FolderDTO implements JsonSerializable
{
    public ?int $id = null;
    public int $vendorId;
    public ?int $parentId = null;
    public string $name;
    public string $slug;
    public string $path;
    public int $sortOrder = 0;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public function __construct(
        int $vendorId,
        string $name,
        ?int $parentId = null,
        ?int $id = null,
        int $sortOrder = 0,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->vendorId = $vendorId;
        $this->parentId = $parentId;
        $this->name = $name;
        $this->slug = sanitize_title($name);
        $this->sortOrder = $sortOrder;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->path = $this->generatePath();
    }

    public static function fromArray(array $row): self
    {
        $dto = new self(
            id: (int) ($row['id'] ?? 0),
            vendorId: (int) $row['vendor_id'],
            parentId: $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            name: $row['name'],
            sortOrder: (int) ($row['sort_order'] ?? 0),
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
        $dto->slug = $row['slug'] ?? $dto->slug;
        $dto->path = $row['path'] ?? $dto->path;
        return $dto;
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'vendor_id' => $this->vendorId,
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'slug' => $this->slug,
            'path' => $this->path,
            'sort_order' => $this->sortOrder,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ], fn($v) => $v !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function generatePath(): string
    {
        return $this->slug;
    }
}
