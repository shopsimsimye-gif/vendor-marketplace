<?php
namespace VMP\Repositories;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRequestRepositoryInterface;

/**
 * مستودع طلبات انضمام البائعين
 * يدير العمليات على جدول vmp_vendor_requests
 */
class VendorRequestRepository implements VendorRequestRepositoryInterface
{
    private string $table;
    private \wpdb $db;
    private string $cache_group = 'vmp_vendor_requests';

    /**
     * Construct functionality helper.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_vendor_requests';
    }

    /**
     * مسح التخزين المؤقت لطلب
     */
    private function clearCache(int $id, int $user_id = 0, string $slug = ''): void
    {
        wp_cache_delete("request_id_{$id}", $this->cache_group);
        if ($user_id) {
            wp_cache_delete("request_user_{$user_id}", $this->cache_group);
        }
        if ($slug) {
            wp_cache_delete("request_slug_{$slug}", $this->cache_group);
        }
        wp_cache_delete('request_stats', $this->cache_group);
    }

    /**
     * إنشاء طلب انضمام جديد
     */
    public function create(array $data): int|false
    {
        // التحقق من الحقول المطلوبة
        $required = ['user_id', 'store_name', 'store_slug'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        $store_slug = sanitize_title($data['store_slug']);

        // التحقق من عدم تكرار الـ slug
        if ($this->slugExists($store_slug)) {
            return false;
        }

        $result = $this->db->insert($this->table, [
            'user_id'            => (int) ($data['user_id'] ?? 0),
            'store_name'         => sanitize_text_field($data['store_name'] ?? ''),
            'store_slug'         => $store_slug,
            'store_description'  => sanitize_textarea_field($data['store_description'] ?? ''),
            'store_address'      => sanitize_textarea_field($data['store_address'] ?? ''),
            'store_phone'        => sanitize_text_field($data['store_phone'] ?? ''),
            'store_email'        => sanitize_email($data['store_email'] ?? ''),
            'whatsapp_number'    => sanitize_text_field($data['whatsapp_number'] ?? ''),
            'store_logo'         => (int) ($data['store_logo'] ?? 0),
            'store_banner'       => (int) ($data['store_banner'] ?? 0),
            'license_file'       => (int) ($data['license_file'] ?? 0),
            'plan_id'            => (int) ($data['plan_id'] ?? 0),
            'status'             => sanitize_text_field($data['status'] ?? 'pending'),
            'admin_notes'        => sanitize_textarea_field($data['admin_notes'] ?? ''),
            'terms_accepted'     => !empty($data['terms_accepted']) ? 1 : 0,
            'terms_accepted_at'  => !empty($data['terms_accepted']) ? current_time('mysql') : null,
            'created_at'         => current_time('mysql'),
            'updated_at'         => current_time('mysql'),
        ]);

        if ($result) {
            $id = (int) $this->db->insert_id;
            $this->clearCache($id, (int) ($data['user_id'] ?? 0), $store_slug);
            return $id;
        }

        return false;
    }

    /**
     * البحث عن طلب بواسطة المعرف
     */
    public function find(int $id): ?object
    {
        $cache_key = "request_id_{$id}";
        $request = wp_cache_get($cache_key, $this->cache_group);

        if (false === $request) {
            $request = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
            );
            if ($request) {
                wp_cache_set($cache_key, $request, $this->cache_group);
                wp_cache_set("request_user_{$request->user_id}", $request, $this->cache_group);
                wp_cache_set("request_slug_{$request->store_slug}", $request, $this->cache_group);
            } else {
                $request = null;
            }
        }

        return $request;
    }

    /**
     * البحث عن طلب بواسطة معرف المستخدم
     */
    public function findByUserId(int $user_id): ?object
    {
        $cache_key = "request_user_{$user_id}";
        $request = wp_cache_get($cache_key, $this->cache_group);

        if (false === $request) {
            $request = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id)
            );
            if ($request) {
                wp_cache_set($cache_key, $request, $this->cache_group);
                wp_cache_set("request_id_{$request->id}", $request, $this->cache_group);
                wp_cache_set("request_slug_{$request->store_slug}", $request, $this->cache_group);
            } else {
                $request = null;
            }
        }

        return $request;
    }

    /**
     * البحث عن طلب بواسطة الـ slug
     */
    public function findBySlug(string $slug): ?object
    {
        $cache_key = "request_slug_{$slug}";
        $request = wp_cache_get($cache_key, $this->cache_group);

        if (false === $request) {
            $request = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE store_slug = %s", $slug)
            );
            if ($request) {
                wp_cache_set($cache_key, $request, $this->cache_group);
                wp_cache_set("request_id_{$request->id}", $request, $this->cache_group);
                wp_cache_set("request_user_{$request->user_id}", $request, $this->cache_group);
            } else {
                $request = null;
            }
        }

        return $request;
    }

    /**
     * تحديث بيانات طلب
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'store_name', 'store_slug', 'store_description', 'store_address',
            'store_phone', 'store_email', 'whatsapp_number', 'store_logo',
            'store_banner', 'license_file', 'plan_id', 'status', 'admin_notes',
            'terms_accepted', 'terms_accepted_at', 'reviewed_at', 'reviewed_by'
        ];

        $update = [];
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
            $request = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
            if ($request) {
                $this->clearCache($id, (int) $request->user_id, $request->store_slug);
            } else {
                $this->clearCache($id);
            }
        }

        return $result;
    }

    /**
     * الموافقة على طلب - إنشاء بائع وتحديث الحالة
     */
    public function approve(int $id, int $admin_id): int|false|\WP_Error
    {
        $request = $this->find($id);
        if (!$request || !in_array($request->status, ['pending', 'submitted', 'under_review'], true)) {
            // إذا كان مقبولاً مسبقاً، نتحقق من وجود سجل البائع
            if ($request && $request->status === 'approved') {
                global $wpdb;
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d", $request->user_id));
                if ($existing) return (int) $existing;
            }
            return new \WP_Error('approve_failed', __('فشلت عملية الموافقة على طلب البائع.', 'vmp'));
        }

        global $wpdb;

        // استخدام معاملة (Transaction) لضمان التكامل
        $wpdb->query('START TRANSACTION');

        try {
            $existing_vendor = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d OR store_slug = %s",
                $request->user_id,
                $request->store_slug
            ));

            if ($existing_vendor) {
                $vendor_id = (int) $existing_vendor->id;
                $result = $wpdb->update($wpdb->prefix . 'vmp_vendors', [
                    'store_name'         => $request->store_name,
                    'store_slug'         => $request->store_slug,
                    'store_description'  => $request->store_description,
                    'store_address'      => $request->store_address,
                    'store_phone'        => $request->store_phone,
                    'store_email'        => $request->store_email,
                    'store_logo'         => $request->store_logo,
                    'store_banner'       => $request->store_banner,
                    'whatsapp_number'    => $request->whatsapp_number,
                    'status'             => 'approved',
                    'subscription_plan'  => $request->plan_id ? 'plan_' . $request->plan_id : 'free',
                    'subscription_status'=> 'active',
                    'updated_at'         => current_time('mysql'),
                ], ['id' => $vendor_id]);
            } else {
                // 1. إنشاء سجل البائع في جدول vmp_vendors
                $result = $wpdb->insert($wpdb->prefix . 'vmp_vendors', [
                    'user_id'            => (int) $request->user_id,
                    'store_name'         => $request->store_name,
                    'store_slug'         => $request->store_slug,
                    'store_description'  => $request->store_description,
                    'store_address'      => $request->store_address,
                    'store_phone'        => $request->store_phone,
                    'store_email'        => $request->store_email,
                    'store_logo'         => $request->store_logo,
                    'store_banner'       => $request->store_banner,
                    'whatsapp_number'    => $request->whatsapp_number,
                    'status'             => 'approved',
                    'subscription_plan'  => $request->plan_id ? 'plan_' . $request->plan_id : 'free',
                    'subscription_status'=> 'active',
                    'subscription_start' => current_time('mysql'),
                    'created_at'         => current_time('mysql'),
                    'updated_at'         => current_time('mysql'),
                ]);

                if (!$result) {
                    throw new \Exception('Failed to create vendor record');
                }
                $vendor_id = (int) $wpdb->insert_id;
            }

            // 2. تحديث حالة الطلب إلى approved
            $wpdb->update($this->table, [
                'status'      => 'approved',
                'reviewed_at' => current_time('mysql'),
                'reviewed_by' => $admin_id,
                'updated_at'  => current_time('mysql'),
            ], ['id' => $id]);

            // 3. إضافة دور vmp_vendor للمستخدم (إذا لم يكن موجوداً)
            $user = get_userdata($request->user_id);
            if ($user && !in_array('vmp_vendor', (array) $user->roles)) {
                $user->add_role('vmp_vendor');
            }

            // 4. حفظ vendor_id في user meta
            update_user_meta($request->user_id, 'vmp_vendor_id', $vendor_id);
            update_user_meta($request->user_id, 'vmp_vendor_status', 'approved');

            $wpdb->query('COMMIT');

            $this->clearCache($id, (int) $request->user_id, $request->store_slug);

            // إطلاق حدث الموافقة
            do_action('vmp_vendor_request_approved', $vendor_id, $id, $admin_id, $request);

            // ── 5. ضمان سجل الاشتراك الافتراضي (الخطة المجانية) إن لم يوجد اشتراك نشط ──
            $has_active_sub = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}vmp_vendor_subscriptions
                 WHERE vendor_id = %d AND status = 'active' LIMIT 1",
                $vendor_id
            ));
            if (!$has_active_sub) {
                $sub_plans_table = $wpdb->prefix . 'vmp_subscription_plans';
                // الخطة المجانية = نشطة وبسعر 0 (الأولوية)، وإلا أول خطة نشطة
                $free_plan = $wpdb->get_row(
                    "SELECT * FROM {$sub_plans_table}
                     WHERE is_active = 1
                     ORDER BY (price = 0) DESC, price ASC, id ASC
                     LIMIT 1"
                );
                if ($free_plan) {
                    $wpdb->insert($wpdb->prefix . 'vmp_vendor_subscriptions', [
                        'vendor_id'        => $vendor_id,
                        'plan_id'          => (int) $free_plan->id,
                        'status'           => 'active',
                        'amount'           => 0,
                        'billing_period'   => $free_plan->billing_period,
                        'billing_interval' => (int) $free_plan->billing_interval,
                        'start_date'       => current_time('mysql'),
                        'end_date'         => date('Y-m-d H:i:s', strtotime('+10 years')),
                        'created_at'       => current_time('mysql'),
                    ]);
                }
            }

            // ── 6. إطلاق حدث الموافقة الحي (Notification + Subscription modules) ──
            // السبب: do_action('vmp_vendor_request_approved') ليس له مستمعون؛ الـ modules
            // الحية تستمع على 'vmp_vendor_approved' عبر EventManager. هذا يفعّل بريد
            // التهاني + إشعار لوحة البائع (Notification::on_vendor_approved).
            // Subscription::on_vendor_approved سيتخطى لأن اشتراكاً نشطاً أُنشئ أعلاه.
            try {
                \VMP\Core\Container::getInstance()->make('event_manager')
                    ->trigger('vmp_vendor_approved', $vendor_id);
            } catch (\Throwable $e) {
                error_log('[VMP] vmp_vendor_approved trigger failed: ' . $e->getMessage());
            }

            return $vendor_id;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[VMP] Vendor request approval failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * رفض طلب مع سبب
     */
    public function reject(int $id, string $reason, int $admin_id): bool
    {
        $request = $this->find($id);
        if (!$request || $request->status !== 'pending') {
            return false;
        }

        $result = $this->update($id, [
            'status'      => 'rejected',
            'admin_notes' => sanitize_textarea_field($reason),
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => $admin_id,
        ]);

        if ($result) {
            // إطلاق حدث الرفض (القديم بلا مستمعين)
            do_action('vmp_vendor_request_rejected', $id, $admin_id, $reason, $request);

            // ── إشعار الرفض الحي ──
            // Notification::on_vendor_rejected يستمع على 'vmp_vendor_rejected' عبر
            // EventManager ويتطلب سجل بائع موجود. نسترجعه من request->user_id؛
            // إن وُجد بائع نطلق الحدث (إشعار/بريد الرفض)، وإلا نتجاهل بأمان.
            $vendor_id = $this->db->get_var($this->db->prepare(
                "SELECT id FROM {$this->db->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
                $request->user_id
            ));
            if ($vendor_id) {
                try {
                    \VMP\Core\Container::getInstance()->make('event_manager')
                        ->trigger('vmp_vendor_rejected', (int) $vendor_id, $reason);
                } catch (\Throwable $e) {
                    error_log('[VMP] vmp_vendor_rejected trigger failed: ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * التحقق من وجود slug مكرر
     */
    public function slugExists(string $slug): bool
    {
        $slug = sanitize_title($slug);
        $count = $this->db->get_var(
            $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE store_slug = %s", $slug)
        );
        return (int) $count > 0;
    }

    /**
     * التحقق من وجود بريد إلكتروني مكرر
     */
    public function emailExists(string $email): bool
    {
        $count = $this->db->get_var(
            $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE store_email = %s", $email)
        );
        return (int) $count > 0;
    }

    /**
     * الحصول على قائمة الطلبات مع التصفية
     */
    public function getAll(array $args = []): array
    {
        $defaults = [
            'status'    => '',
            'limit'     => 50,
            'offset'    => 0,
            'order_by'  => 'created_at',
            'order'     => 'DESC',
            'search'    => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if (!empty($args['status'])) {
            if ($args['status'] === 'pending') {
                $where[] = "status IN ('pending', 'submitted', 'under_review')";
            } else {
                $where[] = 'status = %s';
                $params[] = $args['status'];
            }
        }

        if (!empty($args['search'])) {
            $where[] = '(store_name LIKE %s OR store_slug LIKE %s OR store_email LIKE %s)';
            $search_term = '%' . $this->db->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$args['order_by']} {$args['order']} LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $this->db->get_results($this->db->prepare($sql, $params));
    }

    /**
     * عدد الطلبات حسب الحالة
     */
    public function getCount(string $status = ''): int
    {
        if ($status === 'pending') {
            return (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status IN ('pending', 'submitted', 'under_review')");
        }
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
     * أحدث الطلبات المعلقة
     */
    public function getLatestPending(int $limit = 5): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE status IN ('pending', 'submitted', 'under_review') ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * حذف طلب نهائياً
     */
    public function delete(int $id): bool
    {
        $request = $this->find($id);
        $result = (bool) $this->db->delete($this->table, ['id' => $id]);
        
        if ($result && $request) {
            $this->clearCache($id, (int) $request->user_id, $request->store_slug);
        }
        return $result;
    }

    /**
     * البحث عن طلبات بواسطة اسم المتجر
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
     * إحصاءات سريعة عن الطلبات
     */
    public function getQuickStats(): array
    {
        $cache_key = 'request_stats';
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if (false === $stats) {
            $stats = [
                'total'     => (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table}"),
                'pending'   => (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status IN ('pending', 'submitted', 'under_review')"),
                'approved'  => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = %s", 'approved')),
                'rejected'  => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = %s", 'rejected')),
            ];
            wp_cache_set($cache_key, $stats, $this->cache_group);
        }

        return $stats;
    }
}
