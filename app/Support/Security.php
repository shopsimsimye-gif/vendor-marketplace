<?php
namespace VMP\Support;

defined('ABSPATH') || exit;

/**
 * Security — مساعد مركزي للأمان
 *
 * يوفر:
 * - تنظيف البيانات (Sanitize)
 * - الهروب من المخرجات (Escape)
 * - التحقق من Nonces
 * - Audit Logging
 * - CSRF protection helpers
 */
class Security
{
    private static ?self $instance = null;

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

    // ─── Nonce ────────────────────────────────────────────────────────────────

    /**
     * إنشاء Nonce جديد
     */
    public static function createNonce(string $action): string
    {
        return wp_create_nonce('vmp_' . $action);
    }

    /**
     * التحقق من صحة Nonce
     * يرمي WP_Error إذا فشل التحقق
     *
     * @throws \RuntimeException
     */
    public static function verifyNonce(string $nonce, string $action): void
    {
        if (!wp_verify_nonce($nonce, 'vmp_' . $action)) {
            throw new \RuntimeException(__('انتهت صلاحية الطلب. يرجى تحديث الصفحة والمحاولة مرة أخرى.', 'vmp'));
        }
    }

    // ─── CSRF Protection ──────────────────────────────────────────────────────

    /**
     * إنشاء توكن CSRF لنموذج
     */
    public static function csrfToken(string $action = 'default'): string
    {
        return self::createNonce('csrf_' . $action);
    }

    /**
     * إنشاء حقل إدخال مخفي يحتوي على توكن CSRF
     */
    public static function csrfField(string $action = 'default'): string
    {
        $token = self::csrfToken($action);
        return sprintf('<input type="hidden" name="vmp_csrf_token" value="%s">', esc_attr($token));
    }

    /**
     * التحقق من توكن CSRF من الطلب
     */
    public static function verifyCsrfToken(?string $token = null, string $action = 'default'): void
    {
        $token = $token ?? ($_POST['vmp_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        self::verifyNonce($token, 'csrf_' . $action);
    }

    // ─── Sanitize ─────────────────────────────────────────────────────────────

    /**
     * تنظيف نص عادي
     */
    public static function sanitizeText(string $value): string
    {
        return sanitize_text_field(wp_unslash($value));
    }

    /**
     * تنظيف بريد إلكتروني
     */
    public static function sanitizeEmail(string $value): string
    {
        return sanitize_email(wp_unslash($value));
    }

    /**
     * تنظيف URL
     */
    public static function sanitizeUrl(string $value): string
    {
        return esc_url_raw(wp_unslash($value));
    }

    /**
     * تنظيف عدد صحيح
     */
    public static function sanitizeInt(mixed $value): int
    {
        return (int) $value;
    }

    /**
     * تنظيف عدد عشري
     */
    public static function sanitizeFloat(mixed $value): float
    {
        return (float) $value;
    }

    /**
     * تنظيف slug
     */
    public static function sanitizeSlug(string $value): string
    {
        return sanitize_title(wp_unslash($value));
    }

    /**
     * تنظيف HTML مع السماح بتاغات آمنة محددة
     */
    public static function sanitizeHtml(string $value, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            $allowedTags = wp_kses_allowed_html('post');
        }
        return wp_kses(wp_unslash($value), $allowedTags);
    }

    /**
     * تنظيف مصفوفة من البيانات النصية
     */
    public static function sanitizeArray(array $data): array
    {
        return array_map(static function ($value) {
            if (is_array($value)) {
                return self::sanitizeArray($value);
            }
            return is_string($value) ? self::sanitizeText($value) : $value;
        }, $data);
    }

    // ─── Escape ───────────────────────────────────────────────────────────────

    /**
     * الهروب من نص للعرض في HTML
     */
    public static function escHtml(string $value): string
    {
        return esc_html($value);
    }

    /**
     * الهروب من قيمة لاستخدامها في attribute HTML
     */
    public static function escAttr(string $value): string
    {
        return esc_attr($value);
    }

    /**
     * الهروب من URL
     */
    public static function escUrl(string $value): string
    {
        return esc_url($value);
    }

    /**
     * الهروب من نص JavaScript
     */
    public static function escJs(string $value): string
    {
        return esc_js($value);
    }

    // ─── Rate Limiting (Simple) ───────────────────────────────────────────────

    /**
     * تحديد معدل العمليات الحساسة (تسجيل دخول، تسجيل بائع، إلخ)
     *
     * @param string $action    اسم العملية
     * @param int    $userId    معرف المستخدم (0 للزوار، يستخدم IP)
     * @param int    $limit     الحد الأقصى للمحاولات
     * @param int    $window    النافذة الزمنية بالثواني
     * @return bool true إذا تجاوز الحد
     */
    public static function isRateLimited(string $action, int $userId = 0, int $limit = 5, int $window = 300): bool
    {
        $identifier = $userId ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key        = 'vmp_rl_' . md5($action . '_' . $identifier);

        $attempts = (int) get_transient($key);
        if ($attempts >= $limit) {
            return true;
        }

        set_transient($key, $attempts + 1, $window);
        return false;
    }

    // ─── Audit Log ───────────────────────────────────────────────────────────

    /**
     * تسجيل حدث أمني في جدول اللوجز
     *
     * @param string $action  وصف الحدث (e.g. 'vendor_approved')
     * @param array  $context بيانات سياقية إضافية
     */
    public static function auditLog(string $action, array $context = []): void
    {
        global $wpdb;

        $logsTable = $wpdb->prefix . 'vmp_logs';

        $wpdb->insert($logsTable, [
            'level'      => 'audit',
            'message'    => sanitize_text_field($action),
            'context'    => wp_json_encode(array_merge($context, [
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_id' => get_current_user_id(),
            ])),
            'user_id'    => get_current_user_id() ?: null,
            'ip_address' => self::anonymizeIp($_SERVER['REMOTE_ADDR'] ?? ''),
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * إخفاء آخر أوكتيت من IP لحماية الخصوصية (GDPR)
     */
    public static function anonymizeIp(string $ip): string
    {
        if (!$ip) {
            return '';
        }
        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }
        // IPv6 — يُبقي على أول 4 كتل فقط
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . '::';
        }
        return $ip;
    }

    // ─── Input Validation ─────────────────────────────────────────────────────

    /**
     * التحقق من أن قيمة موجودة في قائمة مسموح بها (whitelist)
     */
    public static function allowedValue(mixed $value, array $allowed, mixed $default = null): mixed
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * التحقق من قوة كلمة المرور
     * يعيد true إذا كانت كلمة المرور تستوفي المتطلبات الدنيا
     */
    public static function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password);
    }
}
