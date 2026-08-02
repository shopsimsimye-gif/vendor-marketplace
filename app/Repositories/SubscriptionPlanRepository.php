<?php
namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\SubscriptionPlanRepositoryInterface;

/**
 * مستودع خطط الاشتراك
 * مسؤول فقط عن CRUD وقاعدة البيانات. لا Business Logic.
 */
class SubscriptionPlanRepository implements SubscriptionPlanRepositoryInterface
{
    private string $table;
    private \wpdb $db;
    private string $cache_group = 'vmp_subscription_plans';

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_subscription_plans';
    }

    /**
     * مسح التخزين المؤقت
     */
    private function clearCache(int $id = 0, string $slug = ''): void
    {
        if ($id) {
            wp_cache_delete("plan_id_{$id}", $this->cache_group);
        }
        if ($slug) {
            wp_cache_delete("plan_slug_{$slug}", $this->cache_group);
        }
        wp_cache_delete('plans_all', $this->cache_group);
        wp_cache_delete('plans_active', $this->cache_group);
        wp_cache_delete('plan_count', $this->cache_group);
    }

    /**
     * Find functionality helper.
     *
     * @param int $id Description index.
     * @return ?object Output payload.
     */
    public function find(int $id): ?object
    {
        $cache_key = "plan_id_{$id}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
            );
            if ($row) {
                wp_cache_set($cache_key, $row, $this->cache_group);
                wp_cache_set("plan_slug_{$row->slug}", $row, $this->cache_group);
            } else {
                $row = null;
            }
        }

        return $row;
    }

    /**
     * FindBySlug functionality helper.
     *
     * @param string $slug Description index.
     * @return ?object Output payload.
     */
    public function findBySlug(string $slug): ?object
    {
        $cache_key = "plan_slug_{$slug}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = %s", $slug)
            );
            if ($row) {
                wp_cache_set($cache_key, $row, $this->cache_group);
                wp_cache_set("plan_id_{$row->id}", $row, $this->cache_group);
            } else {
                $row = null;
            }
        }

        return $row;
    }

    /**
     * GetAll functionality helper.
     *
     * @param bool $active_only Description index.
     * @return array Output payload.
     */
    public function getAll(bool $active_only = true): array
    {
        $cache_key = $active_only ? 'plans_active' : 'plans_all';
        $rows = wp_cache_get($cache_key, $this->cache_group);

        if (false === $rows) {
            $where = $active_only ? $this->db->prepare("WHERE is_active = %d", 1) : "";
            $rows  = $this->db->get_results(
                $this->db->prepare("SELECT * FROM {$this->table} {$where} ORDER BY sort_order ASC, price ASC")
            );
            wp_cache_set($cache_key, $rows, $this->cache_group, HOUR_IN_SECONDS);
        }

        return $rows;
    }

    /**
     * Create functionality helper.
     *
     * @param array $data Description index.
     * @return int|false Output payload.
     */
    public function create(array $data): int|false
    {
        $features = $data['features'] ?? [];
        if (!is_array($features)) {
            $features = [];
        }

        $result = $this->db->insert($this->table, [
            'name'             => sanitize_text_field($data['name'] ?? ''),
            'slug'             => sanitize_title($data['slug'] ?? $data['name'] ?? ''),
            'description'      => sanitize_textarea_field($data['description'] ?? ''),
            'price'            => (float) ($data['price'] ?? 0),
            'billing_period'   => sanitize_text_field($data['billing_period'] ?? 'month'),
            'billing_interval' => (int) ($data['billing_interval'] ?? 1),
            'max_products'     => (int) ($data['max_products'] ?? 10),
            'commission_rate'  => (float) ($data['commission_rate'] ?? 10),
            'features'         => wp_json_encode($features, JSON_UNESCAPED_UNICODE),
            'is_active'        => (int) ($data['is_active'] ?? 1),
            'sort_order'       => (int) ($data['sort_order'] ?? 0),
            'created_at'       => current_time('mysql'),
        ]);

        if ($result) {
            $this->clearCache();
        }

        return $result ? (int) $this->db->insert_id : false;
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
        $allowed = [
            'name', 'description', 'price', 'billing_period', 'billing_interval',
            'max_products', 'commission_rate', 'features', 'is_active', 'sort_order',
        ];

        $update = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if ($field === 'features') {
                    $features      = is_array($data[$field]) ? $data[$field] : [];
                    $update[$field]= wp_json_encode($features, JSON_UNESCAPED_UNICODE);
                } else {
                    $update[$field] = $data[$field];
                }
            }
        }

        if (empty($update)) {
            return false;
        }

        $result = (bool) $this->db->update($this->table, $update, ['id' => $id]);

        if ($result) {
            $plan = $this->db->get_row($this->db->prepare("SELECT slug FROM {$this->table} WHERE id = %d", $id));
            $this->clearCache($id, $plan ? $plan->slug : '');
        }

        return $result;
    }

    /**
     * حذف ناعم (تعطيل الخطة)
     */
    public function delete(int $id): bool
    {
        return $this->update($id, ['is_active' => 0]);
    }

    /**
     * حذف نهائي من قاعدة البيانات
     */
    public function forceDelete(int $id): bool
    {
        $plan   = $this->find($id);
        $result = (bool) $this->db->delete($this->table, ['id' => $id]);

        if ($result && $plan) {
            $this->clearCache($id, $plan->slug);
        }

        return $result;
    }

    /**
     * GetFeatures functionality helper.
     *
     * @param int $id Description index.
     * @return array Output payload.
     */
    public function getFeatures(int $id): array
    {
        $plan = $this->find($id);
        if (!$plan) {
            return [];
        }

        $features = json_decode($plan->features ?? '[]', true);
        return is_array($features) ? $features : [];
    }

    /**
     * HasFeature functionality helper.
     *
     * @param int $plan_id Description index.
     * @param string $feature_key Description index.
     * @return bool Output payload.
     */
    public function hasFeature(int $plan_id, string $feature_key): bool
    {
        $features = $this->getFeatures($plan_id);
        return !empty($features[$feature_key]);
    }

    /**
     * CanAddProduct functionality helper.
     *
     * @param int $plan_id Description index.
     * @param int $current_count Description index.
     * @return bool Output payload.
     */
    public function canAddProduct(int $plan_id, int $current_count): bool
    {
        $plan = $this->find($plan_id);
        if (!$plan) {
            return false;
        }

        $features = $this->getFeatures($plan_id);
        if (!empty($features['unlimited_products'])) {
            return true;
        }

        $max = (int) $plan->max_products;
        return $max === 0 || $current_count < $max;
    }

    /**
     * GetCount functionality helper.
     *
     * @return int Output payload.
     */
    public function getCount(): int
    {
        $cache_key = 'plan_count';
        $count = wp_cache_get($cache_key, $this->cache_group);

        if (false === $count) {
            $count = (int) $this->db->get_var(
                $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE is_active = %d", 1)
            );
            wp_cache_set($cache_key, $count, $this->cache_group, HOUR_IN_SECONDS);
        }

        return $count;
    }
}