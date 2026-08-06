<?php
namespace VMP\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * Interface MiddlewareInterface
 *
 * [QA 2026-08-06] كان هذا الملف يحتوي نسخة كاملة من class NonceMiddleware
 * بدلاً من تعريف الواجهة، ما كان سيُسبب "Interface not found" fatal عند تحميل
 * أي فئة implements MiddlewareInterface (Authentication/RateLimit/VendorMiddleware).
 * أُعيد تعريف الواجهة الحقيقية؛ NonceMiddleware.php يبقى كما هو.
 *
 * ملاحظة: الواجهة تُفرض فقط `handle` (العقد المشترك لكل الـ middleware في الـ pipeline).
 * `__invoke` (للاستخدام كـ permission_callback) ليس إلزامياً لكل تنفيذ —
 * مثلاً RateLimitMiddleware لا يحتاجه. لذلك بقيت خارج الواجهة رغم امتلاك
 * Nonce/Authentication/VendorMiddleware لها.
 *
 * @package VMP\Http\Middleware
 */
interface MiddlewareInterface
{
    /**
     * للاستخدام في pipeline (middleware stack).
     */
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error;
}
