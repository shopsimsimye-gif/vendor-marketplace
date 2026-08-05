<?php
/**
 * VendorMiddleware — يتحقق من أن المستخدم الحالي هو بائع معتمد
 *
 * يُستخدم لحماية endpoints خاصة بالبائعين
 *
 * @package VMP\Http\Middleware
 * @since 3.0.0
 */

namespace VMP\Http\Middleware;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;

class VendorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private VendorRepositoryInterface $vendorRepository
    ) {}

    /**
     * للاستخدام في pipeline (middleware stack)
     */
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error
    {
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
     * للاستخدام مباشرةً كـ permission_callback في register_rest_route
     */
    public function __invoke(\WP_REST_Request $request): bool|\WP_Error
    {
        $vendorId = $this->checkVendorStatus();

        if ($vendorId === 0) {
            return new \WP_Error(
                'forbidden',
                __('هذا المورد متاح للبائعين المعتمدين فقط.', 'vmp'),
                ['status' => 403]
            );
        }

        // حقن vendor_id حتى في وضع الـ permission_callback
        $request->set_param('current_vendor_id', $vendorId);

        return true;
    }

    /**
     * التحقق من حالة البائع عبر Repository
     *
     * @return int vendor_id إذا كان معتمداً، 0 خلاف ذلك
     */
    private function checkVendorStatus(): int
    {
        if (!is_user_logged_in()) {
            return 0;
        }

        $userId = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($userId);

        if (!$vendor || !isset($vendor->status) || $vendor->status !== 'approved') {
            return 0;
        }

        return (int) $vendor->id;
    }
}
