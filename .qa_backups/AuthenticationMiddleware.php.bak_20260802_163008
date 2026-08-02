<?php
namespace VMP\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * AuthenticationMiddleware — يتحقق من تسجيل دخول المستخدم للـ REST API
 *
 * يُضاف كـ permission_callback لـ endpoints التي تتطلب مصادقة
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * Handle functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @param callable $next Description index.
     * @return mixed Output payload.
     */
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error
    {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'unauthorized',
                __('يجب تسجيل الدخول أولاً للوصول إلى هذا المورد.', 'vmp'),
                ['status' => 401]
            );
        }

        return $next($request);
    }

    /**
     * يمكن استخدامه مباشرةً كـ permission_callback في register_rest_route
     */
    public function __invoke(\WP_REST_Request $request): bool|\WP_Error
    {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'unauthorized',
                __('يجب تسجيل الدخول أولاً للوصول إلى هذا المورد.', 'vmp'),
                ['status' => 401]
            );
        }

        return true;
    }
}
