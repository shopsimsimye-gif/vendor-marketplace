<?php

declare(strict_types=1);

namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * @property-read int|null $id
 * @property-read int $vendorId
 * @property-read int $attachmentId
 * @property-read int|null $folderId
 * @property-read string $type
 * @property-read string $mimeType
 * @property-read int $fileSize
 * @property-read int|null $width
 * @property-read int|null $height
 * @property-read array|null $metadata
 * @property-read string|null $createdAt
 * @property-read string|null $updatedAt
 */
class MediaDTO extends BaseDTO
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly int $vendorId = 0,
        public readonly int $attachmentId = 0,
        public readonly ?int $folderId = null,
        public readonly string $type = 'image',
        public readonly string $mimeType = '',
        public readonly int $fileSize = 0,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?array $metadata = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $metadata = isset($data['metadata']) ? json_decode($data['metadata'], true) : null;

        return new static(
            id: isset($data['id']) ? (int) $data['id'] : null,
            vendorId: (int) ($data['vendor_id'] ?? 0),
            attachmentId: (int) ($data['attachment_id'] ?? 0),
            folderId: isset($data['folder_id']) ? (int) $data['folder_id'] : null,
            type: (string) ($data['type'] ?? 'image'),
            mimeType: (string) ($data['mime_type'] ?? ''),
            fileSize: (int) ($data['file_size'] ?? 0),
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            metadata: $metadata,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendorId,
            'attachment_id' => $this->attachmentId,
            'folder_id' => $this->folderId,
            'type' => $this->type,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'width' => $this->width,
            'height' => $this->height,
            'metadata' => $this->metadata ? json_encode($this->metadata) : null,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
