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
     * @return string Output payload.
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // تأخذ أول IP فقط من قائمة مفصولة بفاصلة
                return trim(explode(',', $ip)[0]);
            }
        }

        return '0.0.0.0';
    }
}
