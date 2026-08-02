<?php
namespace VMP\Http\Middleware;

defined('ABSPATH') || exit;

use VMP\Support\Security;
use VMP\Exceptions\AuthenticationException;

/**
 * NonceMiddleware — يتحقق من صحة توكن CSRF/Nonce للطلبات الآمنة
 *
 * يُستخدم لحماية Endpoints في REST API أو طلبات Ajax الحساسة التي تغير حالة البيانات.
 */
class NonceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $action = 'default'
    ) {}

    /**
     * Handle functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @param callable $next Description index.
     * @throws \AuthenticationException Diagnostic error when triggered.
     * @return mixed Output payload.
     */
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error
    {
        // إذا كان الطلب من نوع القراءة (GET, HEAD, OPTIONS) فلا حاجة للتحقق
        if (in_array($request->get_method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        try {
            // محاولة جلب التوكن من الهيدر أو البودي
            $token = $request->get_header('X-WP-Nonce') 
                  ?? $request->get_header('X-CSRF-TOKEN') 
                  ?? $request->get_param('vmp_csrf_token');

            if (!$token) {
                throw new AuthenticationException(__('رمز الأمان مفقود (CSRF Token).', 'vmp'));
            }

            // التحقق إما عبر نظام النونس الافتراضي لووردبريس للـ REST API أو نظام الـ CSRF المخصص
            if (!wp_verify_nonce($token, 'wp_rest') && !wp_verify_nonce($token, 'vmp_csrf_' . $this->action)) {
                throw new AuthenticationException(__('رمز الأمان غير صالح أو منتهي الصلاحية.', 'vmp'));
            }

            return $next($request);

        } catch (AuthenticationException $e) {
            return new \WP_Error('rest_forbidden', $e->getMessage(), ['status' => 403]);
        }
    }

    /**
     * لاستخدامه مباشرة كدالة Permission Callback
     */
    public function __invoke(\WP_REST_Request $request): bool|\WP_Error
    {
        $response = $this->handle($request, fn() => true);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return true;
    }
}
