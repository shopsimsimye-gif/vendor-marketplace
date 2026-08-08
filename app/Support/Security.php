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
    /**
     * Rate Limiter ذري (atomic) عبر جدول DB — مقاوم لتزويـر الـ race condition.
     *
     * يستخدم `INSERT ... ON DUPLICATE KEY UPDATE` لزيادة العداد بشكل ذري داخل
     * MySQL، بدلاً من get/set transient (not atomic) الذي كان يسمح بمرور عمليتين
     * متزامنتين في نفس اللحظة. يُستخدم `identifier` = userId إن وُجد وإلا IP.
     *
     * @return bool true إذا تجاوز الحد
     */
    public static function isRateLimited(string $action, int $userId = 0, int $limit = 5, int $window = 300): bool
    {
        global $wpdb;

        $identifier  = $userId ?: self::getClientIp();
        $bucket      = md5($action . '|' . $identifier);
        $table       = $wpdb->prefix . 'vmp_rate_limits';
        $now         = time();
        $windowStart = $now - ($now % $window);

        // bump ذري — لا read-modify-write تحت التزامن
        // eslint تعليق إعادة القيم في DUPLICATE لتحديث last_seen
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (`bucket`, `window_start`, `count`, `last_seen`)
             VALUES (%s, %d, 1, %d)
             ON DUPLICATE KEY UPDATE `count` = `count` + 1, `last_seen` = VALUES(`last_seen`)",
            $bucket, $windowStart, $now
        ));

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT `count` FROM {$table} WHERE `bucket` = %s AND `window_start` = %d",
            $bucket, $windowStart
        ));

        // تنظيف دوري متساهل (1% من الطلبات) لكبح نمو الجدول.
        if (mt_rand(1, 100) === 1) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE `last_seen` < %d",
                $now - DAY_IN_SECONDS
            ));
        }

        return $count > $limit;
    }

    /**
     * جلب IP العميل الحقيقي دون الثقة في headers مزورة.
     *
     * نثق بـ CF-Connecting-IP / X-Forwarded-For فقط إذا كان الطلب قادماً فعلاً
     * من نطاقات Cloudflare الرسمية (REMOTE_ADDR ضمن CIDR). وإلا بل REMOTE_ADDR.
     */
    public static function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $cloudflareRanges = [
            // IPv4
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22',
            '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
            '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22',
            '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            // IPv6
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32',
            '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];

        $trustedProxies = apply_filters('vmp_trusted_proxies', $cloudflareRanges);

        if (self::ipInRanges($remoteAddr, $trustedProxies)) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $first = trim(explode(',', (string) $_SERVER['HTTP_CF_CONNECTING_IP'])[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * تحقق من أن IP ضمن أي نطاق CIDR (يدعم IPv4/IPv6).
     */
    private static function ipInRanges(string $ip, array $ranges): bool
    {
        $packedIp = @inet_pton($ip);
        if ($packedIp === false) {
            return false;
        }

        foreach ($ranges as $range) {
            if (!is_string($range) || strpos($range, '/') === false) {
                continue;
            }

            [$subnet, $bits] = array_pad(explode('/', $range, 2), 2, null);
            $packedSubnet = @inet_pton($subnet);

            if ($packedSubnet === false || strlen($packedSubnet) !== strlen($packedIp)) {
                continue;
            }

            $maxBits    = strlen($packedIp) * 8;
            $maskBits   = $bits !== null ? min((int) $bits, $maxBits) : $maxBits;
            $fullBytes  = intdiv($maskBits, 8);
            $remainBits = $maskBits % 8;

            $match = true;
            for ($i = 0; $i < strlen($packedIp); $i++) {
                if ($i < $fullBytes) {
                    if ($packedIp[$i] !== $packedSubnet[$i]) {
                        $match = false;
                        break;
                    }
                } elseif ($i === $fullBytes && $remainBits > 0) {
                    $maskByte = (0xFF << (8 - $remainBits)) & 0xFF;
                    if ((ord($packedIp[$i]) & $maskByte) !== (ord($packedSubnet[$i]) & $maskByte)) {
                        $match = false;
                        break;
                    }
                }
            }

            if ($match) {
                return true;
            }
        }

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
