<?php
/**
 * NonceMiddleware — يتحقق من صحة توكن CSRF/Nonce
 *
 * @package VMP\Http\Middleware
 * @since 3.0.0
 */

namespace VMP\Http\Middleware;

defined('ABSPATH') || exit;

use VMP\Exceptions\AuthenticationException;

class NonceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $action = 'default'
    ) {}

    /**
     * للاستخدام في pipeline (middleware stack)
     */
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error
    {
        $error = $this->verifyNonce($request);
        if ($error) {
            return $error;
        }
        return $next($request);
    }

    /**
     * للاستخدام مباشرةً كـ permission_callback
     */
    public function __invoke(\WP_REST_Request $request): bool|\WP_Error
    {
        $error = $this->verifyNonce($request);
        return $error ?? true;
    }

    /**
     * المنطق المشترك
     */
    private function verifyNonce(\WP_REST_Request $request): ?\WP_Error
    {
        if (in_array($request->get_method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        $token = $request->get_header('X-WP-Nonce')
              ?? $request->get_header('X-CSRF-TOKEN')
              ?? $request->get_param('vmp_csrf_token');

        if (empty($token)) {
            return new \WP_Error(
                'rest_forbidden',
                __('رمز الأمان مفقود (CSRF Token).', 'vmp'),
                ['status' => 403]
            );
        }

        $token = (string) $token;

        if (!wp_verify_nonce($token, 'wp_rest') && !wp_verify_nonce($token, 'vmp_csrf_' . $this->action)) {
            return new \WP_Error(
                'rest_forbidden',
                __('رمز الأمان غير صالح أو منتهي الصلاحية.', 'vmp'),
                ['status' => 403]
            );
        }

        return null;
    }
}
