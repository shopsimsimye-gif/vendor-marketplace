<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Repositories;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Contracts\FolderRepositoryInterface;
use VMP\Modules\Media\DTO\FolderDTO;

class FolderRepository implements FolderRepositoryInterface
{
    protected string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vmp_media_folders';
    }

    public function find(int $id): ?FolderDTO
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ? FolderDTO::fromArray($row) : null;
    }

    public function findByVendor(int $vendorId, int $parentId = 0): array
    {
        global $wpdb;
        $where  = ['vendor_id = %d'];
        $params = [$vendorId];

        if ($parentId > 0) {
            $where[]  = 'parent_id = %d';
            $params[] = $parentId;
        } else {
            $where[]  = '(parent_id = 0 OR parent_id IS NULL)';
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where)
             . ' ORDER BY sort_order ASC, name ASC';

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        return array_map([FolderDTO::class, 'fromArray'], $results);
    }

    public function findByVendorFlat(int $vendorId): array
    {
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE vendor_id = %d ORDER BY path ASC",
                $vendorId
            ),
            ARRAY_A
        );
        return array_map([FolderDTO::class, 'fromArray'], $results);
    }

    public function create(FolderDTO $dto): FolderDTO
    {
        global $wpdb;
        $data = array_filter([
            'vendor_id' => $dto->vendorId,
            'parent_id' => $dto->parentId > 0 ? $dto->parentId : null,
            'name' => $dto->name,
            'slug' => $dto->slug,
            'path' => $dto->path,
            'sort_order' => $dto->sortOrder,
        ], fn($v) => $v !== null);

        $wpdb->insert($this->table, $data);
        return $this->find((int) $wpdb->insert_id);
    }

    public function update(int $id, FolderDTO $dto): bool
    {
        global $wpdb;
        $data = array_filter([
            'parent_id' => $dto->parentId > 0 ? $dto->parentId : null,
            'name' => $dto->name,
            'slug' => $dto->slug,
            'path' => $dto->path,
            'sort_order' => $dto->sortOrder,
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return false;
        }
        return (bool) $wpdb->update($this->table, $data, ['id' => $id], ['%s', '%d', '%s', '%s', '%d'], ['%d']);
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

    public function getTree(int $vendorId): array
    {
        $flat = $this->findByVendorFlat($vendorId);
        return $this->buildTree($flat);
    }

    private function buildTree(array $folders, int $parentId = 0): array
    {
        $tree = [];
        foreach ($folders as $folder) {
            if (($folder->parentId ?? 0) === $parentId) {
                $children = $this->buildTree($folders, $folder->id ?? 0);
                $tree[] = array_merge($folder->toArray(), ['children' => $children]);
            }
        }
        return $tree;
    }

    public function getBreadcrumbs(int $folderId): array
    {
        global $wpdb;
        $breadcrumbs = [];
        $currentId = $folderId;

        while ($currentId > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $currentId),
                ARRAY_A
            );
            if (!$row) break;
            $folder = FolderDTO::fromArray($row);
            array_unshift($breadcrumbs, $folder);
            $currentId = $folder->parentId ?? 0;
        }

        return $breadcrumbs;
    }

    public function move(int $id, int $newParentId): bool
    {
        global $wpdb;
        // Check for circular reference
        if ($this->wouldCreateCycle($id, $newParentId)) {
            return false;
        }

        // Get new parent path
        $newPath = '';
        if ($newParentId > 0) {
            $parent = $this->find($newParentId);
            if ($parent) {
                $newPath = $parent->path . '/' . $this->find($id)->slug;
            }
        }

        return (bool) $wpdb->update(
            $this->table,
            ['parent_id' => $newParentId > 0 ? $newParentId : null, 'path' => $newPath],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );
    }

    private function wouldCreateCycle(int $folderId, int $newParentId): bool
    {
        if ($newParentId <= 0 || $folderId === $newParentId) {
            return $folderId === $newParentId;
        }

        $currentId = $newParentId;
        while ($currentId > 0) {
            if ($currentId === $folderId) {
                return true;
            }
            $row = $this->find($currentId);
            if (!$row) break;
            $currentId = $row->parentId ?? 0;
        }

        return false;
    }

    public function hasChildren(int $id): bool
    {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE parent_id = %d", $id)
        );
        return (int) $count > 0;
    }

    public function getFullPath(int $id): string
    {
        $folder = $this->find($id);
        return $folder ? $folder->path : '';
    }
}
