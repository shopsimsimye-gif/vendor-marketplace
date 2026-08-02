<?php
namespace VMP\Support;

defined('ABSPATH') || exit;

/**
 * CacheManager — طبقة كاش موحدة تدعم:
 *  - Object Cache (wp_cache_*) إذا كان WP Object Cache نشطاً
 *  - Transients كـ fallback
 *
 * استخدام:
 *   $cache = CacheManager::getInstance();
 *   $data  = $cache->remember('vendor_123', 3600, fn() => $db->getVendor(123));
 *   $cache->forget('vendor_123');
 *   $cache->flushGroup('vendors');
 */
class CacheManager
{
    private static ?self $instance = null;

    /** Prefix لجميع مفاتيح الكاش الخاصة بالإضافة */
    private string $prefix = 'vmp_';

    /** مجموعات الكاش المدعومة */
    private string $group = 'vmp';

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    private function __construct() {}

    /**
     * GetInstance functionality helper.
     *
     * @return self Output payload.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * جلب قيمة من الكاش أو تخزينها إذا لم تكن موجودة
     *
     * @param string   $key     مفتاح الكاش
     * @param int      $ttl     مدة الحياة بالثواني (0 = دائم)
     * @param callable $callback دالة تُنتج القيمة إذا لم تُوجَد في الكاش
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== false) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * جلب قيمة من الكاش
     * يعيد false إذا لم تُوجَد
     */
    public function get(string $key): mixed
    {
        $cacheKey = $this->buildKey($key);

        // Object Cache أسرع — نجرّبه أولاً
        $found = false;
        $value = wp_cache_get($cacheKey, $this->group, false, $found);

        if ($found) {
            return $value;
        }

        // Fallback: Transients
        $value = get_transient($cacheKey);
        return $value;
    }

    /**
     * تخزين قيمة في الكاش
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $cacheKey = $this->buildKey($key);

        wp_cache_set($cacheKey, $value, $this->group, $ttl);

        if ($ttl > 0) {
            set_transient($cacheKey, $value, $ttl);
        }

        return true;
    }

    /**
     * حذف قيمة من الكاش
     */
    public function forget(string $key): bool
    {
        $cacheKey = $this->buildKey($key);
        wp_cache_delete($cacheKey, $this->group);
        delete_transient($cacheKey);
        return true;
    }

    /**
     * حذف مجموعة مفاتيح بناءً على نمط (pattern)
     * مفيد لمسح كاش بائع بعد تحديث بياناته
     *
     * @param string $pattern نمط للبحث مثل 'vendor_123_*'
     */
    public function forgetByPattern(string $pattern): int
    {
        global $wpdb;

        $likePattern = $wpdb->esc_like($this->prefix . $pattern) . '%';
        $transientKeys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s",
                '_transient_' . $likePattern
            )
        );

        $deleted = 0;
        foreach ($transientKeys as $optionName) {
            $transientKey = str_replace('_transient_', '', $optionName);
            if (delete_transient($transientKey)) {
                wp_cache_delete($transientKey, $this->group);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * مسح الكاش الخاص بالإضافة بالكامل (للاستخدام عند التحديث)
     */
    public function flush(): void
    {
        wp_cache_flush_group($this->group);
        // حذف Transients بالجملة
        $this->forgetByPattern('');
    }

    /**
     * بناء مفتاح الكاش مع الـ prefix
     */
    private function buildKey(string $key): string
    {
        return $this->prefix . $key;
    }

    // ─── Helpers (Sugar Methods) ──────────────────────────────────────────────

    /**
     * كاش بيانات بائع
     */
    public function vendor(int $vendorId, int $ttl = 3600): self
    {
        // يُرجع $this لدعم method chaining (لا يُستخدم مباشرةً)
        return $this;
    }

    /**
     * مفتاح بائع موحد
     */
    public static function vendorKey(int $vendorId, string $suffix = ''): string
    {
        return 'vendor_' . $vendorId . ($suffix ? '_' . $suffix : '');
    }

    /**
     * مفتاح منتج موحد
     */
    public static function productKey(int $productId, string $suffix = ''): string
    {
        return 'product_' . $productId . ($suffix ? '_' . $suffix : '');
    }

    /**
     * مفتاح قائمة موحد
     */
    public static function listKey(string $entity, array $args = []): string
    {
        return $entity . '_list_' . md5(serialize($args));
    }
}
