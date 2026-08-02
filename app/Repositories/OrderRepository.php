<?php
namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\OrderRepositoryInterface;

/**
 * Class OrderRepository
 *
 * Description of administrative platform component OrderRepository.
 *
 * @package vendor-marketplace
 */
class OrderRepository implements OrderRepositoryInterface
{
    private string $table;
    private \wpdb $db;
    private string $cache_group = 'vmp_orders';

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_vendor_orders';
    }

    /**
     * مسح التخزين المؤقت لطلب
     */
    private function clearCache(int $id): void
    {
        wp_cache_delete("order_id_{$id}", $this->cache_group);
    }

    /**
     * Create functionality helper.
     *
     * @param array $data Description index.
     * @return int|false Output payload.
     */
    public function create(array $data): int|false
    {
        $result = $this->db->insert($this->table, [
            'vendor_id'      => (int) ($data['vendor_id'] ?? 0),
            'order_id'       => (int) ($data['order_id'] ?? 0),
            'parent_order_id'=> (int) ($data['parent_order_id'] ?? 0),
            'status'         => sanitize_text_field($data['status'] ?? 'pending'),
            'total'          => (float) ($data['total'] ?? 0),
            'commission'     => (float) ($data['commission'] ?? 0),
            'vendor_earnings'=> (float) ($data['vendor_earnings'] ?? 0),
            'shipping_cost'  => (float) ($data['shipping_cost'] ?? 0),
            'tax'            => (float) ($data['tax'] ?? 0),
            'created_at'     => current_time('mysql'),
        ]);

        return $result ? (int) $this->db->insert_id : false;
    }

    /**
     * Find functionality helper.
     *
     * @param int $id Description index.
     * @return ?object Output payload.
     */
    public function find(int $id): ?object
    {
        $cache_key = "order_id_{$id}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
            );
            if ($row) {
                wp_cache_set($cache_key, $row, $this->cache_group);
            } else {
                $row = null;
            }
        }

        return $row;
    }

    /**
     * FindByOrderId functionality helper.
     *
     * @param int $order_id Description index.
     * @param int $vendor_id Description index.
     * @return ?object Output payload.
     */
    public function findByOrderId(int $order_id, int $vendor_id): ?object
    {
        $cache_key = "order_wc_{$order_id}_v_{$vendor_id}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->table} WHERE order_id = %d AND vendor_id = %d",
                    $order_id,
                    $vendor_id
                )
            );
            if ($row) {
                wp_cache_set($cache_key, $row, $this->cache_group);
            } else {
                $row = null;
            }
        }

        return $row;
    }

    /**
     * Update functionality helper.
     *
     * @param int $id Description index.
     * @param array $data Description index.
     * @return bool Output payload.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['status', 'total', 'commission', 'vendor_earnings', 'shipping_cost', 'tax'];
        $update  = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) {
            return false;
        }

        $update['updated_at'] = current_time('mysql');
        $result = (bool) $this->db->update($this->table, $update, ['id' => $id]);

        if ($result) {
            $this->clearCache($id);
        }

        return $result;
    }

    /**
     * GetByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param array $args Description index.
     * @return array Output payload.
     */
    public function getByVendor(int $vendor_id, array $args = []): array
    {
        $defaults = ['status' => '', 'limit' => 20, 'offset' => 0];
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
     * GetByParentOrder functionality helper.
     *
     * @param int $parent_order_id Description index.
     * @return array Output payload.
     */
    public function getByParentOrder(int $parent_order_id): array
    {
        return $this->db->get_results(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE parent_order_id = %d", $parent_order_id)
        );
    }

    /**
     * CountByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param string $status Description index.
     * @return int Output payload.
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
     * GetTotalSales functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return float Output payload.
     */
    public function getTotalSales(int $vendor_id): float
    {
        $cache_key = "vendor_{$vendor_id}_total_sales";
        $value = wp_cache_get($cache_key, $this->cache_group);

        if (false === $value) {
            $value = (float) $this->db->get_var(
                $this->db->prepare(
                    "SELECT COALESCE(SUM(total), 0) FROM {$this->table} WHERE vendor_id = %d AND status = 'completed'",
                    $vendor_id
                )
            );
            wp_cache_set($cache_key, $value, $this->cache_group, HOUR_IN_SECONDS);
        }

        return $value;
    }

    /**
     * GetTotalEarnings functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return float Output payload.
     */
    public function getTotalEarnings(int $vendor_id): float
    {
        $cache_key = "vendor_{$vendor_id}_total_earnings";
        $value = wp_cache_get($cache_key, $this->cache_group);

        if (false === $value) {
            $value = (float) $this->db->get_var(
                $this->db->prepare(
                    "SELECT COALESCE(SUM(vendor_earnings), 0) FROM {$this->table} WHERE vendor_id = %d AND status = 'completed'",
                    $vendor_id
                )
            );
            wp_cache_set($cache_key, $value, $this->cache_group, HOUR_IN_SECONDS);
        }

        return $value;
    }

    /**
     * ✅ إجمالي المبيعات لجميع البائعين
     */
    public function getTotalSalesForAllVendors(): float
    {
        $cache_key = 'all_vendors_total_sales';
        $value = wp_cache_get($cache_key, $this->cache_group);

        if (false === $value) {
            $value = (float) $this->db->get_var(
                $this->db->prepare("SELECT COALESCE(SUM(total), 0) FROM {$this->table} WHERE status = %s", 'completed')
            );
            wp_cache_set($cache_key, $value, $this->cache_group, HOUR_IN_SECONDS);
        }

        return $value;
    }

    /**
     * ✅ إجمالي أرباح جميع البائعين
     */
    public function getTotalEarningsForAllVendors(): float
    {
        $cache_key = 'all_vendors_total_earnings';
        $value = wp_cache_get($cache_key, $this->cache_group);

        if (false === $value) {
            $value = (float) $this->db->get_var(
                "SELECT COALESCE(SUM(vendor_earnings), 0) FROM {$this->table} WHERE status = 'completed'"
            );
            wp_cache_set($cache_key, $value, $this->cache_group, HOUR_IN_SECONDS);
        }

        return $value;
    }

    /**
     * ✅ إجمالي أرباح بائع معين (اسم بديل)
     */
    public function getTotalEarningsByVendor(int $vendor_id): float
    {
        return $this->getTotalEarnings($vendor_id);
    }
}