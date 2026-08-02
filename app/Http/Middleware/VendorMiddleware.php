<?php
namespace VMP\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * VendorMiddleware — يتحقق من أن المستخدم الحالي هو بائع معتمد
 *
 * يُستخدم لحماية endpoints خاصة بالبائعين
 */
class VendorMiddleware implements MiddlewareInterface
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
                __('يجب تسجيل الدخول أولاً.', 'vmp'),
                ['status' => 401]
            );
        }

        $vendorId = $this->checkVendorStatus();
        if ($vendorId === 0) {
            return new \WP_Error(
                'forbidden',
                __('هذا المورد متاح للبائعين المعتمدين فقط.', 'vmp'),
                ['status' => 403]
            );
        }

        // حقن vendor_id في الـ request لاستخدامه لاحقاً
        $request->set_param('current_vendor_id', $vendorId);

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
                __('يجب تسجيل الدخول أولاً.', 'vmp'),
                ['status' => 401]
            );
        }

        if ($this->checkVendorStatus() === 0) {
            return new \WP_Error(
                'forbidden',
                __('هذا المورد متاح للبائعين المعتمدين فقط.', 'vmp'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * CheckVendorStatus functionality helper — [QA 2026-08-02] منطق مشترك
     * بين handle() و __invoke() لمنع تكرار الكود.
     *
     * @return int vendor_id إذا كان المستخدم بائعاً معتمداً، و0 خلاف ذلك.
     */
    private function checkVendorStatus(): int
    {
        $userId   = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);
        $status   = get_user_meta($userId, 'vmp_vendor_status', true);

        if (!$vendorId || $status !== 'approved') {
            return 0;
        }

        return $vendorId;
    }
}
