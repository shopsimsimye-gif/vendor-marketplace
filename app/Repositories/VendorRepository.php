<?php
namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;

/**
 * Class VendorRepository
 *
 * Description of administrative platform component VendorRepository.
 *
 * @package vendor-marketplace
 */
class VendorRepository implements VendorRepositoryInterface
{
    private string $table;
    private \wpdb $db;
    private string $cache_group = 'vmp_vendors';

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_vendors';
    }

    /**
     * مسح التخزين المؤقت لبائع
     */
    private function clearCache(int $id, int $user_id = 0, string $slug = ''): void
    {
        wp_cache_delete("vendor_id_{$id}", $this->cache_group);
        if ($user_id) {
            wp_cache_delete("vendor_user_{$user_id}", $this->cache_group);
        }
        if ($slug) {
            wp_cache_delete("vendor_slug_{$slug}", $this->cache_group);
        }
        wp_cache_delete('vendor_stats', $this->cache_group);
    }

    /**
     * إنشاء بائع جديد
     */
    public function create(array $data): int|false
    {
        $result = $this->db->insert($this->table, [
            'user_id'            => (int) ($data['user_id'] ?? 0),
            'store_name'         => sanitize_text_field($data['store_name'] ?? ''),
            'store_slug'         => sanitize_title($data['store_slug'] ?? $data['store_name'] ?? ''),
            'store_description'  => sanitize_textarea_field($data['store_description'] ?? ''),
            'store_address'      => sanitize_textarea_field($data['store_address'] ?? ''),
            'store_phone'        => sanitize_text_field($data['store_phone'] ?? ''),
            'store_email'        => sanitize_email($data['store_email'] ?? ''),
            'store_logo'         => (int) ($data['store_logo'] ?? 0),
            'store_banner'       => (int) ($data['store_banner'] ?? 0),
            'whatsapp_number'    => sanitize_text_field($data['whatsapp_number'] ?? ''),
            'whatsapp_message'   => sanitize_textarea_field($data['whatsapp_message'] ?? ''),
            'custom_css'         => wp_kses_post($data['custom_css'] ?? ''),
            'status'             => sanitize_text_field($data['status'] ?? 'pending'),
            'is_trusted'         => !empty($data['is_trusted']) ? 1 : 0,
            'balance'            => (float) ($data['balance'] ?? 0),
            'subscription_plan'  => sanitize_text_field($data['subscription_plan'] ?? 'free'),
            'subscription_status'=> sanitize_text_field($data['subscription_status'] ?? 'active'),
            'subscription_start' => sanitize_text_field($data['subscription_start'] ?? null),
            'subscription_expiry'=> sanitize_text_field($data['subscription_expiry'] ?? null),
            'admin_notes'        => sanitize_textarea_field($data['admin_notes'] ?? ''),
            'created_at'         => current_time('mysql'),
            'updated_at'         => current_time('mysql'),
        ]);

        if ($result) {
            $id = (int) $this->db->insert_id;
            $this->clearCache($id, (int) ($data['user_id'] ?? 0), sanitize_title($data['store_slug'] ?? $data['store_name'] ?? ''));
            return $id;
        }

        return false;
    }

    /**
     * البحث عن بائع بواسطة المعرف
     */
    public function find(int $id): ?object
    {
        $cache_key = "vendor_id_{$id}";
        $vendor = wp_cache_get($cache_key, $this->cache_group);

        if (false === $vendor) {
            $vendor = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
            );
            if ($vendor) {
                wp_cache_set($cache_key, $vendor, $this->cache_group);
                wp_cache_set("vendor_user_{$vendor->user_id}", $vendor, $this->cache_group);
                wp_cache_set("vendor_slug_{$vendor->store_slug}", $vendor, $this->cache_group);
            } else {
                $vendor = null;
            }
        }

        return $vendor;
    }

    /**
     * البحث عن بائع بواسطة معرف المستخدم
     */
    public function findByUserId(int $user_id): ?object
    {
        $cache_key = "vendor_user_{$user_id}";
        $vendor = wp_cache_get($cache_key, $this->cache_group);

        if (false === $vendor) {
            $vendor = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = %d", $user_id)
            );
            if ($vendor) {
                wp_cache_set($cache_key, $vendor, $this->cache_group);
                wp_cache_set("vendor_id_{$vendor->id}", $vendor, $this->cache_group);
                wp_cache_set("vendor_slug_{$vendor->store_slug}", $vendor, $this->cache_group);
            } else {
                $vendor = null;
            }
        }

        return $vendor;
    }

    /**
     * البحث عن بائع بواسطة الـ slug
     */
    public function findBySlug(string $slug): ?object
    {
        $cache_key = "vendor_slug_{$slug}";
        $vendor = wp_cache_get($cache_key, $this->cache_group);

        if (false === $vendor) {
            $vendor = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE store_slug = %s", $slug)
            );
            if ($vendor) {
                wp_cache_set($cache_key, $vendor, $this->cache_group);
                wp_cache_set("vendor_id_{$vendor->id}", $vendor, $this->cache_group);
                wp_cache_set("vendor_user_{$vendor->user_id}", $vendor, $this->cache_group);
            } else {
                $vendor = null;
            }
        }

        return $vendor;
    }

    /**
     * تحديث بيانات بائع
     */
    public function update(int $id, array $data): bool
    {
        $vendor = $this->find($id);
        if (!$vendor) {
            return false;
        }

        $allowed = [
            'store_name', 'store_slug', 'store_description', 'store_address',
            'store_latitude', 'store_longitude', 'store_phone', 'store_email',
            'store_logo', 'store_banner', 'store_video', 'whatsapp_number',
            'whatsapp_message', 'social_facebook', 'social_instagram',
            'social_twitter', 'social_youtube', 'custom_css',
            'status', 'is_trusted', 'balance', 'subscription_plan',
            'subscription_status', 'subscription_start', 'subscription_expiry',
            'admin_notes', 'rating', 'review_count', 'total_products',
            'total_orders', 'total_sales',
        ];
        $columns = $this->getTableColumns();

        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data) && in_array($field, $columns, true)) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) {
            return true;
        }

        $update['updated_at'] = current_time('mysql');

        $result = $this->db->update($this->table, $update, ['id' => $id]);

        if ($result !== false) {
            $this->clearCache($id, (int) $vendor->user_id, (string) $vendor->store_slug);
            $updatedVendor = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
            if ($updatedVendor) {
                $this->clearCache($id, (int) $updatedVendor->user_id, (string) $updatedVendor->store_slug);
            }

            return true;
        }

        return false;
    }

    private function getTableColumns(): array
    {
        $cacheKey = 'vendor_table_columns';
        $columns = wp_cache_get($cacheKey, $this->cache_group);

        if ($columns === false) {
            $columns = $this->db->get_col("SHOW COLUMNS FROM `{$this->table}`", 0);
            if (!is_array($columns)) {
                $columns = [];
            }
            wp_cache_set($cacheKey, $columns, $this->cache_group);
        }

        return $columns;
    }

    /**
     * تحديث رصيد البائع
     */
    public function updateBalance(int $id, float $amount): bool
    {
        $vendor = $this->find($id);
        if (!$vendor) {
            return false;
        }

        $new_balance = (float) $vendor->balance + $amount;
        if ($new_balance < 0) {
            $new_balance = 0;
        }

        return $this->update($id, ['balance' => $new_balance]);
    }

    /**
     * الموافقة على بائع
     */
    public function approve(int $id): bool
    {
        return $this->update($id, ['status' => 'approved']);
    }

    /**
     * رفض بائع مع سبب
     */
    public function reject(int $id, string $reason = ''): bool
    {
        $data = ['status' => 'rejected'];
        if (!empty($reason)) {
            $data['admin_notes'] = $reason;
        }
        return $this->update($id, $data);
    }

    /**
     * التحقق من وجود slug مكرر
     */
    public function slugExists(string $slug): bool
    {
        $vendor = $this->findBySlug($slug);
        return $vendor !== null;
    }

    /**
     * التحقق من وجود بريد إلكتروني مكرر
     */
    public function emailExists(string $email): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vmp_vendors';
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE store_email = %s", $email)
        );
        return (int) $count > 0;
    }

    /**
     * الحصول على قائمة البائعين مع خيارات التصفية
     */
    public function getAll(array $args = []): array
    {
        $defaults = ['status' => '', 'limit' => 50, 'offset' => 0, 'order_by' => 'created_at', 'order' => 'DESC'];
        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$args['order_by']} {$args['order']} LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $this->db->get_results($this->db->prepare($sql, $params));
    }

    /**
     * ✅ عدد البائعين (مع إمكانية التصفية حسب الحالة)
     */
    public function getCount(string $status = ''): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        if ($status) {
            $sql .= " WHERE status = %s";
            $params[] = $status;
        }
        if (!empty($params)) {
            return (int) $this->db->get_var($this->db->prepare($sql, $params));
        }
        return (int) $this->db->get_var($sql);
    }

    /**
     * ✅ جلب أحدث البائعين المعلقين
     */
    public function getLatestPending(int $limit = 5): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE status = 'pending' ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * ✅ جلب البائعين النشطين (المعتمدين)
     */
    public function getActiveVendors(int $limit = 50): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s ORDER BY store_name ASC LIMIT %d",
                'approved',
                $limit
            )
        );
    }

    /**
     * حذف بائع نهائياً
     */
    public function delete(int $id): bool
    {
        $vendor = $this->find($id);
        $result = (bool) $this->db->delete($this->table, ['id' => $id]);
        
        if ($result && $vendor) {
            $this->clearCache($id, (int) $vendor->user_id, $vendor->store_slug);
        }
        return $result;
    }

    /**
     * البحث عن بائعين بواسطة اسم المتجر
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE store_name LIKE %s OR store_slug LIKE %s LIMIT %d",
                '%' . $this->db->esc_like($query) . '%',
                '%' . $this->db->esc_like($query) . '%',
                $limit
            )
        );
    }

    /**
     * ✅ الحصول على إحصاءات سريعة عن البائعين (للوحة التحكم)
     */
    public function getQuickStats(): array
    {
        $cache_key = 'vendor_stats';
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if (false === $stats) {
            $stats = [
                'total'         => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table}")),
                'active'        => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = %s", 'approved')),
                'pending'       => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = %s", 'pending')),
                'total_balance' => (float) $this->db->get_var($this->db->prepare("SELECT COALESCE(SUM(balance), 0) FROM {$this->table}")),
            ];
            wp_cache_set($cache_key, $stats, $this->cache_group, HOUR_IN_SECONDS);
        }

        return $stats;
    }

    /**
     * الحصول على إحصاءات تقييمات البائع (العدد + المتوسط)
     *
     * @param int $vendorId معرف البائع
     * @return array{count: int, avg_rating: float}
     */
    public function getReviewStats(int $vendorId): array
    {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT COUNT(*) as count, AVG(rating) as avg_rating
                 FROM {$this->db->prefix}vmp_vendor_reviews
                 WHERE vendor_id = %d AND status = %s",
                $vendorId,
                'approved'
            )
        );

        return [
            'count'      => (int)   ($row->count      ?? 0),
            'avg_rating' => (float) ($row->avg_rating ?? 0.0),
        ];
    }
}
