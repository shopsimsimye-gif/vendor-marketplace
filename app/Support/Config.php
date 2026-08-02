<?php
namespace VMP\Support;

defined('ABSPATH') || exit;

/**
 * نظام إدارة الإعدادات - يقوم بتحميل ملفات الإعدادات من مجلد app/Config/
 * ويوفر واجهة للوصول إلى الإعدادات عبر النقاط (dot notation)
 */
class Config
{
    private static ?Config $instance = null;
    private array $config = [];
    private string $configPath;

    /**
     * المُنشئ خاص (Singleton)
     */
    private function __construct(string $configPath)
    {
        $this->configPath = $configPath;
        $this->loadConfigs();
    }

    /**
     * الحصول على المثيل الوحيد
     */
    public static function getInstance(string $configPath = ''): self
    {
        if (self::$instance === null) {
            self::$instance = new self($configPath);
        }
        return self::$instance;
    }

    /**
     * تحميل جميع ملفات الإعدادات من المجلد
     */
    private function loadConfigs(): void
    {
        if (!is_dir($this->configPath)) {
            return;
        }

        $files = glob($this->configPath . '/*.php');
        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->config[$name] = require $file;
        }
    }

    /**
     * الحصول على قيمة إعداد معين باستخدام dot notation
     * مثال: config('app.name') أو config('commission.default_rate')
     *
     * @param string $key مفتاح الإعداد (مثل 'app.name')
     * @param mixed $default القيمة الافتراضية إذا لم يكن موجوداً
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * تعيين قيمة إعداد معين
     *
     * @param string $key مفتاح الإعداد
     * @param mixed $value القيمة الجديدة
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $target = &$this->config;

        foreach ($keys as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }

        $target = $value;
    }

    /**
     * الحصول على جميع الإعدادات
     */
    public function all(): array
    {
        return $this->config;
    }
}