<?php
namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\CommissionRepositoryInterface;

/**
 * Class CommissionRepository
 *
 * Description of administrative platform component CommissionRepository.
 *
 * @package vendor-marketplace
 */
class CommissionRepository implements CommissionRepositoryInterface
{
    private string $table;
    private \wpdb $db;
    private string $cache_group = 'vmp_commissions';

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_commissions';
    }

    /**
     * مسح التخزين المؤقت للعمولات
     */
    private function clearCache(int $id = 0): void
    {
        if ($id) {
            wp_cache_delete("commission_{$id}", $this->cache_group);
        }
        wp_cache_delete('admin_stats', $this->cache_group);
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
            'vendor_id'        => (int) ($data['vendor_id'] ?? 0),
            'order_id'         => (int) ($data['order_id'] ?? 0),
            'vendor_order_id'  => isset($data['vendor_order_id']) ? (int) $data['vendor_order_id'] : null,
            'product_id'       => (int) ($data['product_id'] ?? 0),
            'amount'           => (float) ($data['amount'] ?? 0),
            'commission_rate'  => (float) ($data['commission_rate'] ?? 10),
            'commission_amount'=> (float) ($data['commission_amount'] ?? 0),
            'vendor_amount'    => (float) ($data['vendor_amount'] ?? 0),
            'status'           => sanitize_text_field($data['status'] ?? 'pending'),
            'paid_at'          => isset($data['paid_at']) ? sanitize_text_field($data['paid_at']) : null,
            'created_at'       => current_time('mysql'),
        ]);

        if ($result) {
            $this->clearCache();
        }

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
        $cache_key = "commission_{$id}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
            );
            $row = $row ?: null;
            if ($row) {
                wp_cache_set($cache_key, $row, $this->cache_group);
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
        $allowed = ['status', 'paid_at'];
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
            $this->clearCache($id);
        }

        return $result;
    }

    /**
     * MarkAsPaid functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function markAsPaid(int $id): bool
    {
        return $this->update($id, ['status' => 'paid', 'paid_at' => current_time('mysql')]);
    }

    /**
     * MarkBulkAsPaid functionality helper.
     *
     * @param array $ids Description index.
     * @return int Output payload.
     */
    public function markBulkAsPaid(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql          = "UPDATE {$this->table} SET status = 'paid', paid_at = NOW() WHERE id IN ($placeholders)";
        $affected     = (int) $this->db->query($this->db->prepare($sql, $ids));

        if ($affected > 0) {
            foreach ($ids as $id) {
                wp_cache_delete("commission_{$id}", $this->cache_group);
            }
            wp_cache_delete('admin_stats', $this->cache_group);
        }

        return $affected;
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
     * GetAllPending functionality helper.
     *
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getAllPending(int $limit = 100): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT c.*, v.store_name
                 FROM {$this->table} c
                 JOIN {$this->db->prefix}vmp_vendors v ON c.vendor_id = v.id
                 WHERE c.status = 'pending'
                 ORDER BY c.created_at ASC
                 LIMIT %d",
                $limit
            )
        );
    }

    /**
     * GetAdminStats functionality helper.
     *
     * @return array Output payload.
     */
    public function getAdminStats(): array
    {
        $cache_key = 'admin_stats';
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if (false === $stats) {
            $stats = [
                'total'   => (float) $this->db->get_var($this->db->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM {$this->table}")),
                'pending' => (float) $this->db->get_var($this->db->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM {$this->table} WHERE status = %s", 'pending')),
                'paid'    => (float) $this->db->get_var($this->db->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM {$this->table} WHERE status = %s", 'paid')),
                'count'   => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table}")),
            ];
            wp_cache_set($cache_key, $stats, $this->cache_group, HOUR_IN_SECONDS);
        }

        return $stats;
    }

    /**
     * ✅ إجمالي العمولات (مع إمكانية التصفية حسب الحالة)
     */
    public function getTotalCommissions(string $status = ''): float
    {
        $sql    = "SELECT COALESCE(SUM(commission_amount), 0) FROM {$this->table}";
        $params = [];

        if ($status) {
            $sql     .= " WHERE status = %s";
            $params[] = $status;
        }

        if (!empty($params)) {
            return (float) $this->db->get_var($this->db->prepare($sql, $params));
        }

        return (float) $this->db->get_var($sql);
    }

    /**
     * GetTotalByVendorAndPeriod functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param string $date_from Description index.
     * @param string $date_to Description index.
     * @return array Output payload.
     */
    public function getTotalByVendorAndPeriod(int $vendor_id, string $date_from, string $date_to): array
    {
        return (array) $this->db->get_row(
            $this->db->prepare(
                "SELECT
                    COALESCE(SUM(commission_amount), 0) AS total_commissions,
                    COALESCE(SUM(vendor_amount), 0) AS total_vendor_earnings,
                    COUNT(*) AS total_orders
                 FROM {$this->table}
                 WHERE vendor_id = %d
                   AND created_at BETWEEN %s AND %s
                   AND status = 'paid'",
                $vendor_id,
                $date_from,
                $date_to
            )
        );
    }

    /**
     * GetMonthlyStats functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $months Description index.
     * @return array Output payload.
     */
    public function getMonthlyStats(int $vendor_id, int $months = 6): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    DATE_FORMAT(created_at, '%%Y-%%m') AS month,
                    COALESCE(SUM(vendor_amount), 0) AS earnings,
                    COUNT(*) AS orders
                 FROM {$this->table}
                 WHERE vendor_id = %d AND status = %s
                 GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
                 ORDER BY month DESC
                 LIMIT %d",
                $vendor_id,
                'paid',
                $months
            )
        );
    }
}