<?php
namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\WithdrawalRepositoryInterface;

/**
 * Class WithdrawalRepository
 *
 * مسؤول فقط عن CRUD وقاعدة البيانات الخاصة بطلبات السحب.
 * أي Business Logic يجب أن تكون في WithdrawalService.
 *
 * @package VMP\Repositories
 */
class WithdrawalRepository implements WithdrawalRepositoryInterface
{
    private string $table;
    private \wpdb $db;
    private string $cache_group = 'vmp_withdrawals';

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_withdrawals';
    }

    /**
     * مسح التخزين المؤقت لطلب سحب
     */
    private function clearCache(int $id): void
    {
        wp_cache_delete("withdrawal_{$id}", $this->cache_group);
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
            'vendor_id'     => (int) ($data['vendor_id'] ?? 0),
            'amount'        => (float) ($data['amount'] ?? 0),
            'status'        => 'pending',
            'method'        => sanitize_text_field($data['method'] ?? 'bank_transfer'),
            'method_details'=> json_encode($data['method_details'] ?? [], JSON_UNESCAPED_UNICODE),
            'notes'         => sanitize_textarea_field($data['notes'] ?? ''),
            'created_at'    => current_time('mysql'),
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
        $cache_key = "withdrawal_{$id}";
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
     * Update functionality helper.
     *
     * @param int $id Description index.
     * @param array $data Description index.
     * @return bool Output payload.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['status', 'notes', 'processed_by', 'processed_at'];
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
     * CountByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return int Output payload.
     */
    public function countByVendor(int $vendor_id): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE vendor_id = %d";
        return (int) $this->db->get_var($this->db->prepare($sql, $vendor_id));
    }

    /**
     * GetPending functionality helper.
     *
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getPending(int $limit = 100): array
    {
        return $this->db->get_results($this->db->prepare(
            "SELECT w.*, v.store_name
             FROM {$this->table} w
             JOIN {$this->db->prefix}vmp_vendors v ON w.vendor_id = v.id
             WHERE w.status = %s
             ORDER BY w.created_at DESC
             LIMIT %d",
            'pending',
            $limit
        ));
    }

    /**
     * Approve functionality helper.
     *
     * @param int $id Description index.
     * @param int $processed_by Description index.
     * @return bool Output payload.
     */
    public function approve(int $id, int $processed_by): bool
    {
        return $this->update($id, [
            'status'       => 'approved',
            'processed_by' => $processed_by,
            'processed_at' => current_time('mysql'),
        ]);
    }

    /**
     * Reject functionality helper.
     *
     * @param int $id Description index.
     * @param int $processed_by Description index.
     * @param string $reason Description index.
     * @return bool Output payload.
     */
    public function reject(int $id, int $processed_by, string $reason = ''): bool
    {
        return $this->update($id, [
            'status'       => 'rejected',
            'processed_by' => $processed_by,
            'processed_at' => current_time('mysql'),
            'notes'        => $reason,
        ]);
    }
}
