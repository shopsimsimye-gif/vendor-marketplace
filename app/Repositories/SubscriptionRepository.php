<?php
namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\SubscriptionRepositoryInterface;
use VMP\Contracts\SubscriptionPlanRepositoryInterface;

/**
 * مستودع اشتراكات البائعين
 * مسؤول فقط عن CRUD وقاعدة البيانات. لا Business Logic.
 */
class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    private string $table;
    private string $plans_table;
    private \wpdb $db;
    private string $cache_group = 'vmp_subscriptions';
    private SubscriptionPlanRepositoryInterface $planRepository;

    /**
     *   Construct functionality helper.
     *
     * @param SubscriptionPlanRepositoryInterface $planRepository Description index.
     * @return void Output payload.
     */
    public function __construct(SubscriptionPlanRepositoryInterface $planRepository)
    {
        global $wpdb;
        $this->db             = $wpdb;
        $this->table          = $wpdb->prefix . 'vmp_vendor_subscriptions';
        $this->plans_table    = $wpdb->prefix . 'vmp_subscription_plans';
        $this->planRepository = $planRepository;
    }

    /**
     * مسح التخزين المؤقت
     */
    private function clearCache(int $id = 0, int $vendor_id = 0): void
    {
        if ($id) {
            wp_cache_delete("sub_{$id}", $this->cache_group);
        }
        if ($vendor_id) {
            wp_cache_delete("sub_active_vendor_{$vendor_id}", $this->cache_group);
            wp_cache_delete("sub_pending_change_vendor_{$vendor_id}", $this->cache_group);
        }
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
            'plan_id'          => (int) ($data['plan_id'] ?? 0),
            'status'           => sanitize_text_field($data['status'] ?? 'active'),
            'amount'           => (float) ($data['amount'] ?? 0),
            'billing_period'   => sanitize_text_field($data['billing_period'] ?? 'month'),
            'billing_interval' => (int) ($data['billing_interval'] ?? 1),
            'start_date'       => sanitize_text_field($data['start_date'] ?? current_time('mysql')),
            'end_date'         => sanitize_text_field($data['end_date'] ?? ''),
            'trial_end_date'   => sanitize_text_field($data['trial_end_date'] ?? ''),
            'payment_method'   => sanitize_text_field($data['payment_method'] ?? ''),
            'payment_details'  => json_encode($data['payment_details'] ?? [], JSON_UNESCAPED_UNICODE),
            'created_at'       => current_time('mysql'),
        ]);

        if ($result) {
            $this->clearCache(0, (int) ($data['vendor_id'] ?? 0));
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
        $cache_key = "sub_{$id}";
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
     * FindActiveByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return ?object Output payload.
     */
    public function findActiveByVendor(int $vendor_id): ?object
    {
        $cache_key = "sub_active_vendor_{$vendor_id}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE vendor_id = %d AND status = 'active'
                     ORDER BY created_at DESC LIMIT 1",
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
        $allowed = ['status', 'end_date', 'cancelled_at', 'payment_method', 'payment_details'];
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
            $sub = $this->db->get_row($this->db->prepare("SELECT vendor_id FROM {$this->table} WHERE id = %d", $id));
            $this->clearCache($id, $sub ? (int) $sub->vendor_id : 0);
        }

        return $result;
    }

    /**
     * Cancel functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function cancel(int $id): bool
    {
        return $this->update($id, ['status' => 'cancelled', 'cancelled_at' => current_time('mysql')]);
    }

    /**
     * GetByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getByVendor(int $vendor_id, int $limit = 10): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table}
                 WHERE vendor_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $vendor_id,
                $limit
            )
        );
    }

    /**
     * GetExpired functionality helper.
     *
     * @return array Output payload.
     */
    public function getExpired(): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table}
                 WHERE status = %s
                 AND end_date < NOW()
                 AND end_date != %s",
                'active',
                '0000-00-00 00:00:00'
            )
        );
    }

    /**
     * GetExpiringSoon functionality helper.
     *
     * @param int $days Description index.
     * @return array Output payload.
     */
    public function getExpiringSoon(int $days = 7): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT s.*, v.store_name, v.user_id, p.name as plan_name
                 FROM {$this->table} s
                 JOIN {$this->db->prefix}vmp_vendors v ON s.vendor_id = v.id
                 JOIN {$this->plans_table} p ON s.plan_id = p.id
                 WHERE s.status = %s
                 AND s.end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY)",
                'active',
                $days
            )
        );
    }

    /**
     * ✅ طلب تغيير الخطة (إنشاء طلب pending_change)
     */
    public function requestPlanChange(int $vendor_id, int $new_plan_id): int|false
    {
        $existing = $this->db->get_var(
            $this->db->prepare(
                "SELECT id FROM {$this->table} WHERE vendor_id = %d AND status = %s",
                $vendor_id,
                'pending_change'
            )
        );

        if ($existing) {
            return false;
        }

        $current         = $this->findActiveByVendor($vendor_id);
        $current_plan_id = $current ? (int) $current->plan_id : 0;

        $result = $this->db->insert($this->table, [
            'vendor_id'      => $vendor_id,
            'plan_id'        => $new_plan_id,
            'status'         => 'pending_change',
            'amount'         => 0,
            'billing_period' => 'month',
            'billing_interval'=> 1,
            'start_date'     => current_time('mysql'),
            'end_date'       => date('Y-m-d H:i:s', strtotime('+1 month')),
            'payment_details'=> json_encode([
                'requested_at'    => current_time('mysql'),
                'current_plan_id' => $current_plan_id,
                'request_type'    => 'upgrade',
            ], JSON_UNESCAPED_UNICODE),
            'created_at'     => current_time('mysql'),
        ]);

        if ($result) {
            $this->clearCache(0, $vendor_id);
        }

        return $result ? (int) $this->db->insert_id : false;
    }

    /**
     * ✅ الموافقة على طلب تغيير الخطة
     */
    public function approvePlanChange(int $subscription_id): bool
    {
        $subscription = $this->find($subscription_id);
        if (!$subscription || $subscription->status !== 'pending_change') {
            return false;
        }

        $current = $this->findActiveByVendor((int) $subscription->vendor_id);
        if ($current) {
            $this->cancel((int) $current->id);
        }

        $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
        $update   = [
            'status'         => 'active',
            'end_date'       => $end_date,
            'payment_details'=> json_encode([
                'approved_at' => current_time('mysql'),
                'approved_by' => get_current_user_id(),
            ], JSON_UNESCAPED_UNICODE),
        ];

        $result = (bool) $this->db->update($this->table, $update, ['id' => $subscription_id]);

        if ($result) {
            $this->clearCache($subscription_id, (int) $subscription->vendor_id);
        }

        return $result;
    }

    /**
     * ✅ رفض طلب تغيير الخطة
     */
    public function rejectPlanChange(int $subscription_id, string $reason = ''): bool
    {
        $subscription = $this->find($subscription_id);
        if (!$subscription || $subscription->status !== 'pending_change') {
            return false;
        }

        $update = [
            'status'         => 'rejected_change',
            'payment_details'=> json_encode([
                'rejected_at' => current_time('mysql'),
                'rejected_by' => get_current_user_id(),
                'reason'      => $reason,
            ], JSON_UNESCAPED_UNICODE),
        ];

        $result = (bool) $this->db->update($this->table, $update, ['id' => $subscription_id]);

        if ($result) {
            $this->clearCache($subscription_id, (int) $subscription->vendor_id);
        }

        return $result;
    }

    /**
     * ✅ جلب طلبات تغيير الخطة المعلقة
     */
    public function getPendingPlanChanges(int $limit = 50): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT s.*, v.store_name, v.user_id, p.name as plan_name, p.price as plan_price
                 FROM {$this->table} s
                 JOIN {$this->db->prefix}vmp_vendors v ON s.vendor_id = v.id
                 JOIN {$this->plans_table} p ON s.plan_id = p.id
                 WHERE s.status = %s
                 ORDER BY s.created_at ASC
                 LIMIT %d",
                'pending_change',
                $limit
            )
        );
    }

    /**
     * ✅ جلب طلب تغيير خطة لبائع معين
     */
    public function getPendingPlanChangeByVendor(int $vendor_id): ?object
    {
        $cache_key = "sub_pending_change_vendor_{$vendor_id}";
        $row = wp_cache_get($cache_key, $this->cache_group);

        if (false === $row) {
            $row = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE vendor_id = %d AND status = 'pending_change'",
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
     * ✅ حذف اشتراك نهائياً
     */
    public function forceDelete(int $id): bool
    {
        $sub    = $this->find($id);
        $result = (bool) $this->db->delete($this->table, ['id' => $id]);

        if ($result && $sub) {
            $this->clearCache($id, (int) $sub->vendor_id);
        }

        return $result;
    }

    /**
     * ✅ التحقق من إمكانية إضافة منتج
     * تفويض المنطق إلى SubscriptionPlanRepository عبر الـ Interface المحقونة.
     */
    public function canAddProduct(int $vendor_id, int $current_count = 0): bool
    {
        $active = $this->findActiveByVendor($vendor_id);

        if (!$active) {
            $free_plan = $this->planRepository->findBySlug('free');
            if (!$free_plan) {
                return $current_count < 10;
            }
            return $this->planRepository->canAddProduct((int) $free_plan->id, $current_count);
        }

        return $this->planRepository->canAddProduct((int) $active->plan_id, $current_count);
    }
}