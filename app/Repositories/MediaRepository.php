<?php

declare(strict_types=1);

namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\MediaRepositoryInterface;
use VMP\DTO\MediaDTO;

class MediaRepository implements MediaRepositoryInterface
{
    protected string $table;
    private const ALLOWED_FILTERS = ['type', 'folder_id'];

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vmp_media';
    }

    public function find(int $id): ?MediaDTO
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ? MediaDTO::fromArray($row) : null;
    }

    public function findByVendor(int $vendorId, array $filters = []): array
    {
        global $wpdb;
        $where  = ['vendor_id = %d'];
        $params = [$vendorId];

        foreach ($filters as $key => $value) {
            if (!in_array($key, self::ALLOWED_FILTERS, true)) {
                continue;
            }
            if ((string) $value === '') {
                continue;
            }
            $where[]  = "{$key} = %s";
            $params[] = $value;
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where)
             . ' ORDER BY created_at DESC';

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        return array_map([MediaDTO::class, 'fromArray'], $results);
    }

    public function findByAttachment(int $attachmentId): ?MediaDTO
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE attachment_id = %d", $attachmentId),
            ARRAY_A
        );
        return $row ? MediaDTO::fromArray($row) : null;
    }

    public function create(MediaDTO $dto): MediaDTO
    {
        global $wpdb;
        $data = array_filter([
            'vendor_id' => $dto->vendorId,
            'attachment_id' => $dto->attachmentId,
            'folder_id' => $dto->folderId,
            'type' => $dto->type,
            'mime_type' => $dto->mimeType,
            'file_size' => $dto->fileSize,
            'width' => $dto->width,
            'height' => $dto->height,
            'metadata' => $dto->metadata ? json_encode($dto->metadata) : null,
        ], fn($v) => $v !== null);

        $wpdb->insert($this->table, $data);
        return $this->find((int) $wpdb->insert_id);
    }

    public function update(int $id, MediaDTO $dto): bool
    {
        global $wpdb;
        $data = array_filter([
            'folder_id' => $dto->folderId,
            'type' => $dto->type,
            'metadata' => $dto->metadata ? json_encode($dto->metadata) : null,
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return false;
        }
        return (bool) $wpdb->update($this->table, $data, ['id' => $id], null, ['%d']);
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($this->table, ['id' => $id], ['%d']);
    }

    public function deleteByVendor(int $vendorId): int
    {
        global $wpdb;
        return (int) $wpdb->query(
            $wpdb->prepare("DELETE FROM {$this->table} WHERE vendor_id = %d", $vendorId)
        );
    }

    public function countByVendor(int $vendorId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE vendor_id = %d", $vendorId)
        );
    }

    public function paginate(int $vendorId, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;
        $offset = ($page - 1) * $perPage;
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE vendor_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $vendorId, $perPage, $offset
            ),
            ARRAY_A
        );
        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE vendor_id = %d", $vendorId)
        );
        return [
            'data' => array_map([MediaDTO::class, 'fromArray'], $results),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }
}
