<?php
namespace VMP\Support\Cache;

defined('ABSPATH') || exit;

/**
 * تخزين مؤقت للبيانات القادمة من المستودعات
 */
class RepositoryCache
{
    private const GROUP = 'vmp_repositories';
    private const TTL = 300; // 5 دقائق

    /**
     * الحصول على بيانات من الكاش أو تنفيذ callback لحسابها
     */
    public static function remember(string $key, callable $callback, int $ttl = self::TTL)
    {
        $value = Manager::get($key, self::GROUP);

        if (false !== $value) {
            return $value;
        }

        $value = $callback();
        Manager::set($key, $value, $ttl, self::GROUP);
        Manager::add_key_to_group($key, self::GROUP);

        return $value;
    }

    /**
     * مسح كاش مستودع معين
     */
    public static function clear(string $key): void
    {
        Manager::delete($key, self::GROUP);
    }

    /**
     * مسح كاش جميع المستودعات
     */
    public static function clearAll(): void
    {
        Manager::flush(self::GROUP);
    }
}