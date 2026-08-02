<?php
namespace VMP\Support\Cache;

defined('ABSPATH') || exit;

/**
 * مدير التخزين المؤقت المركزي
 * يدعم Object Cache (Redis/Memcached) و Transient API كحل بديل
 */
class Manager
{
    private const GROUP = 'vmp';
    private const DEFAULT_TTL = 300; // 5 دقائق

    /**
     * الحصول على قيمة من الكاش
     *
     * @param string $key مفتاح الكاش
     * @param string $group مجموعة الكاش
     * @return mixed القيمة المخزنة أو false إذا لم تكن موجودة
     */
    public static function get(string $key, string $group = self::GROUP)
    {
        // محاولة الحصول من Object Cache أولاً
        if (function_exists('wp_cache_get')) {
            $value = wp_cache_get($key, $group);
            if (false !== $value) {
                return $value;
            }
        }

        // محاولة الحصول من Transient API (كحل بديل)
        $transient_key = self::get_transient_key($key, $group);
        $value = get_transient($transient_key);
        if (false !== $value) {
            // تخزين في Object Cache لاستخدامه مستقبلاً
            if (function_exists('wp_cache_set')) {
                wp_cache_set($key, $value, $group, self::DEFAULT_TTL);
            }
            return $value;
        }

        return false;
    }

    /**
     * تخزين قيمة في الكاش
     *
     * @param string $key مفتاح الكاش
     * @param mixed $value القيمة المراد تخزينها
     * @param int $ttl مدة الصلاحية بالثواني
     * @param string $group مجموعة الكاش
     * @return bool نجاح أو فشل العملية
     */
    public static function set(string $key, $value, int $ttl = self::DEFAULT_TTL, string $group = self::GROUP): bool
    {
        // تخزين في Object Cache
        $result = false;
        if (function_exists('wp_cache_set')) {
            $result = wp_cache_set($key, $value, $group, $ttl);
        }

        // تخزين في Transient API كحل بديل
        $transient_key = self::get_transient_key($key, $group);
        set_transient($transient_key, $value, $ttl);

        return $result;
    }

    /**
     * حذف قيمة من الكاش
     *
     * @param string $key مفتاح الكاش
     * @param string $group مجموعة الكاش
     * @return bool نجاح أو فشل العملية
     */
    public static function delete(string $key, string $group = self::GROUP): bool
    {
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($key, $group);
        }
        $transient_key = self::get_transient_key($key, $group);
        return delete_transient($transient_key);
    }

    /**
     * مسح جميع قيم الكاش في مجموعة معينة
     *
     * @param string $group مجموعة الكاش
     * @return bool نجاح أو فشل العملية
     */
    public static function flush(string $group = self::GROUP): bool
    {
        // مسح Object Cache (إذا كان يدعم فلترة المجموعة)
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group($group);
        }

        // حل بديل: مسح المفاتيح المعروفة
        $keys = self::get_group_keys($group);
        foreach ($keys as $key) {
            self::delete($key, $group);
        }

        return true;
    }

    /**
     * الحصول على مفتاح Transient
     */
    private static function get_transient_key(string $key, string $group): string
    {
        return 'vmp_' . md5($group . '_' . $key);
    }

    /**
     * الحصول على مفاتيح مجموعة معينة (للحل البديل)
     */
    private static function get_group_keys(string $group): array
    {
        $keys = get_option('vmp_cache_keys_' . $group, []);
        return is_array($keys) ? $keys : [];
    }

    /**
     * إضافة مفتاح إلى قائمة مفاتيح المجموعة
     */
    public static function add_key_to_group(string $key, string $group = self::GROUP): void
    {
        $keys = get_option('vmp_cache_keys_' . $group, []);
        if (!is_array($keys)) {
            $keys = [];
        }
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            update_option('vmp_cache_keys_' . $group, $keys);
        }
    }
}