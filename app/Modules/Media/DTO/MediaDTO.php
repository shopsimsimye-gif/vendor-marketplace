<?php

declare(strict_types=1);

namespace VMP\Modules\Media\DTO;

defined('ABSPATH') || exit;

use JsonSerializable;

class MediaDTO implements JsonSerializable
{
    public ?int $id = null;
    public int $vendorId;
    public int $attachmentId;
    public ?int $folderId = null;
    public string $type; // image, video, document, other
    public string $mimeType;
    public int $fileSize;
    public ?int $width = null;
    public ?int $height = null;
    public ?array $metadata = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public function __construct(
        int $vendorId,
        int $attachmentId,
        string $type,
        string $mimeType,
        int $fileSize,
        ?int $width = null,
        ?int $height = null,
        ?array $metadata = null,
        ?int $folderId = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->vendorId = $vendorId;
        $this->attachmentId = $attachmentId;
        $this->folderId = $folderId;
        $this->type = $type;
        $this->mimeType = $mimeType;
        $this->fileSize = $fileSize;
        $this->width = $width;
        $this->height = $height;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            vendorId: (int) $row['vendor_id'],
            attachmentId: (int) $row['attachment_id'],
            folderId: $row['folder_id'] !== null ? (int) $row['folder_id'] : null,
            type: $row['type'],
            mimeType: $row['mime_type'],
            fileSize: (int) $row['file_size'],
            width: $row['width'] !== null ? (int) $row['width'] : null,
            height: $row['height'] !== null ? (int) $row['height'] : null,
            metadata: $row['metadata'] ? json_decode($row['metadata'], true) : null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'vendor_id' => $this->vendorId,
            'attachment_id' => $this->attachmentId,
            'folder_id' => $this->folderId,
            'type' => $this->type,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'width' => $this->width,
            'height' => $this->height,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ], fn($v) => $v !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
