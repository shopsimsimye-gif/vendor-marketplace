<?php
namespace VMP\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * RateLimitMiddleware — يحدد معدل الطلبات لـ REST API
 *
 * يمنع DDOS وسوء الاستخدام عبر تتبع الطلبات في Transients
 * الإعدادات الافتراضية: 60 طلب كل 60 ثانية
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxRequests = 60,
        private int $windowSeconds = 60
    ) {}

    /**
     * Handle functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @param callable $next Description index.
     * @return mixed Output payload.
     */
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error
    {
        $ip  = $this->getClientIp();
        $key = 'vmp_rate_' . md5($ip . $request->get_route());

        $data = get_transient($key);

        if ($data === false) {
            $data = ['count' => 0, 'reset_at' => time() + $this->windowSeconds];
        }

        if (time() >= $data['reset_at']) {
            $data = ['count' => 0, 'reset_at' => time() + $this->windowSeconds];
        }

        $data['count']++;
        set_transient($key, $data, $this->windowSeconds);

        if ($data['count'] > $this->maxRequests) {
            return new \WP_Error(
                'rate_limit_exceeded',
                __('لقد تجاوزت حد الطلبات المسموح به. حاول مرة أخرى لاحقاً.', 'vmp'),
                [
                    'status'      => 429,
                    'retry_after' => $data['reset_at'] - time(),
                ]
            );
        }

        $response = $next($request);

        // إضافة headers معلوماتية
        if ($response instanceof \WP_REST_Response) {
            $response->header('X-RateLimit-Limit', (string) $this->maxRequests);
            $response->header('X-RateLimit-Remaining', (string) max(0, $this->maxRequests - $data['count']));
            $response->header('X-RateLimit-Reset', (string) $data['reset_at']);
        }

        return $response;
    }

    /**
     * GetClientIp functionality helper.
     *
     * [QA 2026-08-02] نثق بـ forwarded headers فقط إذا كان الطلب قادماً فعلاً من
     * Cloudflare (REMOTE_ADDR ضمن نطاقات CF الرسمية). خلاف ذلك نستخدم REMOTE_ADDR
     * مباشرة — كان الوثوق بـ X-Forwarded-For/X-Real-IP من أي مصدر يسمح بتجاوز
     * كامل لـ Rate Limit عبر تزوير الـ header.
     *
     * @return string Output payload.
     */
    private function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // نطاقات Cloudflare الرسمية (IPv4 + IPv6) — https://www.cloudflare.com/ips/
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

        if ($this->ipInRanges($remoteAddr, $trustedProxies)) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                return trim(explode(',', (string) $_SERVER['HTTP_CF_CONNECTING_IP'])[0]);
            }

            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            }
        }

        return $remoteAddr;
    }

    /**
     * IpInRanges functionality helper — يتحقق من أن IP ضمن نطاق CIDR (IPv4/IPv6).
     *
     * @param string $ip Output payload.
     * @param array $ranges Output payload.
     * @return bool Output payload.
     */
    private function ipInRanges(string $ip, array $ranges): bool
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
}
