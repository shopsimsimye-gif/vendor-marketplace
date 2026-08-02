<?php
namespace VMP\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * الواجهة الأساسية للـ Middleware
 * تُطبّق على REST API endpoints
 */
interface MiddlewareInterface
{
    /**
     * @param \WP_REST_Request  $request
     * @param callable          $next     الـ Middleware التالي أو الـ Handler
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error;
}
