<?php
namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\ProductRepositoryInterface;

/**
 * Class ProductRepository
 *
 * Description of administrative platform component ProductRepository.
 *
 * @package vendor-marketplace
 */
class ProductRepository implements ProductRepositoryInterface
{
    private string $table;
    private \wpdb $db;
    private string $cache_group = 'vmp_products';

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_vendor_products';
    }

    /**
     * مسح التخزين المؤقت لمنتج
     */
    private function clearCache(int $id, int $product_id = 0): void
    {
        wp_cache_delete("product_id_{$id}", $this->cache_group);
        if ($product_id) {
            wp_cache_delete("product_wc_{$product_id}", $this->cache_group);
        }
    }

    /**
     * ربط منتج ببائع
     */
    public function create(int $vendor_id, int $product_id, array $data = []): int|false
    {
        $result = $this->db->insert($this->table, [
            'vendor_id'   => $vendor_id,
            'product_id'  => $product_id,
            'status'      => sanitize_text_field($data['status'] ?? 'pending'),
            'is_featured' => !empty($data['is_featured']) ? 1 : 0,
            'created_at'  => current_time('mysql'),
        ]);

        return $result ? (int) $this->db->insert_id : false;
    }

    /**
     * البحث بواسطة المعرف الداخلي
     */
    public function find(int $id): ?object
    {
        $cache_key = "product_id_{$id}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
            );
            if ($row) {
                wp_cache_set($cache_key, $row, $this->cache_group);
                wp_cache_set("product_wc_{$row->product_id}", $row, $this->cache_group);
            } else {
                $row = null;
            }
        }

        return $row;
    }

    /**
     * البحث بواسطة معرف المنتج (WooCommerce product_id)
     */
    public function findByProductId(int $product_id): ?object
    {
        $cache_key = "product_wc_{$product_id}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE product_id = %d", $product_id)
            );
            if ($row) {
                wp_cache_set($cache_key, $row, $this->cache_group);
                wp_cache_set("product_id_{$row->id}", $row, $this->cache_group);
            } else {
                $row = null;
            }
        }

        return $row;
    }

    /**
     * تحديث بيانات منتج
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['status', 'is_featured', 'admin_notes'];
        $update  = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) {
            return false;
        }

        $result = (bool) $this->db->update($this->table, $update, ['id' => $id]);

        if ($result) {
            $row = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
            if ($row) {
                $this->clearCache($id, (int) $row->product_id);
            }
        }

        return $result;
    }

    /**
     * حذف منتج من مستودع البائع
     */
    public function delete(int $id): bool
    {
        $row    = $this->find($id);
        $result = (bool) $this->db->delete($this->table, ['id' => $id]);

        if ($result && $row) {
            $this->clearCache($id, (int) $row->product_id);
        }

        return $result;
    }

    /**
     * Approve functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function approve(int $id): bool
    {
        return $this->update($id, ['status' => 'approved']);
    }

    /**
     * Reject functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function reject(int $id): bool
    {
        return $this->update($id, ['status' => 'rejected']);
    }

    /**
     * جلب منتجات بائع معين
     */
    public function getByVendor(int $vendor_id, array $args = []): array
    {
        $defaults = ['status' => '', 'limit' => 50, 'offset' => 0];
        $args     = wp_parse_args($args, $defaults);

        $where  = ['vendor_id = %d'];
        $params = [$vendor_id];

        if (!empty($args['status'])) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $sql          = "SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[]     = (int) $args['limit'];
        $params[]     = (int) $args['offset'];

        return $this->db->get_results($this->db->prepare($sql, $params));
    }

    /**
     * عدد منتجات بائع معين
     */
    public function countByVendor(int $vendor_id, string $status = ''): int
    {
        $sql    = "SELECT COUNT(*) FROM {$this->table} WHERE vendor_id = %d";
        $params = [$vendor_id];

        if ($status) {
            $sql     .= ' AND status = %s';
            $params[] = $status;
        }

        return (int) $this->db->get_var($this->db->prepare($sql, $params));
    }

    /**
     * جلب المنتجات المعلقة للمراجعة
     */
    public function getPending(int $limit = 100): array
    {
        return $this->db->get_results($this->db->prepare(
            "SELECT vp.*, v.store_name FROM {$this->table} vp
             JOIN {$this->db->prefix}vmp_vendors v ON vp.vendor_id = v.id
             WHERE vp.status = %s
             ORDER BY vp.created_at DESC
             LIMIT %d",
            'pending',
            $limit
        ));
    }

    /**
     * جلب المنتجات المميزة لبائع
     */
    public function getFeatured(int $vendor_id, int $limit = 5): array
    {
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->table} WHERE vendor_id = %d AND is_featured = %d AND status = %s ORDER BY created_at DESC LIMIT %d",
            $vendor_id,
            1,
            'approved',
            $limit
        ));
    }
}
