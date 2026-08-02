<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\ProductRepositoryInterface;
use VMP\Http\Middleware\RateLimitMiddleware;
use VMP\Http\Middleware\VendorMiddleware;
use VMP\Support\CacheManager;

/**
 * RestVendorController — يتولى معالجة طلبات WordPress REST API
 *
 * Endpoints:
 *  GET  /vmp/v1/vendors                         — قائمة البائعين (عام)
 *  GET  /vmp/v1/vendors/{id}                    — بيانات بائع محدد (عام)
 *  GET  /vmp/v1/vendors/{id}/products           — منتجات بائع (عام)
 *  GET  /vmp/v1/vendors/me                      — بيانات البائع الحالي (مصادق)
 *
 * ملاحظة: لا يرث من BaseController لأن REST API لها آليتها الخاصة.
 */
class RestVendorController
{
    private RateLimitMiddleware $rateLimit;
    private VendorMiddleware    $vendorGuard;
    private CacheManager        $cache;

    public function __construct(
        private VendorRepositoryInterface  $vendorRepository,
        private ProductRepositoryInterface $productRepository
    ) {
        $this->rateLimit   = new RateLimitMiddleware(60, 60);
        $this->vendorGuard = new VendorMiddleware();
        $this->cache       = CacheManager::getInstance();
    }

    /**
     * تسجيل مسارات REST API
     */
    public function registerRoutes(): void
    {
        // ─── Public Endpoints ─────────────────────────────────────────────

        register_rest_route('vmp/v1', '/vendors', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getVendors'],
            'permission_callback' => '__return_true',
            'args'                => [
                'limit'  => ['type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                'search' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        register_rest_route('vmp/v1', '/vendors/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getVendor'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        register_rest_route('vmp/v1', '/vendors/(?P<id>\d+)/products', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getVendorProducts'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id'     => ['type' => 'integer', 'required' => true],
                'limit'  => ['type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
            ],
        ]);

        // ─── Authenticated Endpoints ──────────────────────────────────────

        register_rest_route('vmp/v1', '/vendors/me', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getCurrentVendor'],
            'permission_callback' => $this->vendorGuard,
        ]);
    }

    /**
     * GET /vmp/v1/vendors — قائمة البائعين المعتمدين
     */
    public function getVendors(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit  = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');
        $search = sanitize_text_field($request->get_param('search'));

        $cacheKey = CacheManager::listKey('vendors', ['limit' => $limit, 'offset' => $offset, 'search' => $search]);
        $data = $this->cache->remember($cacheKey, 300, function () use ($limit, $offset, $search) {
            $vendors = $this->vendorRepository->getAll([
                'status' => 'approved',
                'limit'  => $limit,
                'offset' => $offset,
                'search' => $search,
            ]);

            return array_map(fn($vendor) => [
                'id'                => (int) $vendor->id,
                'store_name'        => $vendor->store_name,
                'store_slug'        => $vendor->store_slug,
                'store_description' => $vendor->store_description,
                'rating'            => (float) $vendor->rating,
                'review_count'      => (int) $vendor->review_count,
                'is_trusted'        => (bool) $vendor->is_trusted,
                'total_products'    => (int) $vendor->total_products,
            ], $vendors);
        });

        return new \WP_REST_Response([
            'data'   => $data,
            'total'  => count($data),
            'limit'  => $limit,
            'offset' => $offset,
        ], 200);
    }

    /**
     * GET /vmp/v1/vendors/{id} — بيانات بائع محدد
     */
    public function getVendor(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        $cacheKey = CacheManager::vendorKey($id, 'rest');
        $data = $this->cache->remember($cacheKey, 600, function () use ($id) {
            $vendor = $this->vendorRepository->find($id);

            if (!$vendor || $vendor->status !== 'approved') {
                return null;
            }

            return [
                'id'                => (int) $vendor->id,
                'store_name'        => $vendor->store_name,
                'store_slug'        => $vendor->store_slug,
                'store_description' => $vendor->store_description,
                'store_address'     => $vendor->store_address,
                'store_phone'       => $vendor->store_phone,
                'store_email'       => $vendor->store_email,
                'rating'            => (float) $vendor->rating,
                'review_count'      => (int) $vendor->review_count,
                'is_trusted'        => (bool) $vendor->is_trusted,
                'total_products'    => (int) $vendor->total_products,
                'total_orders'      => (int) $vendor->total_orders,
            ];
        });

        if ($data === null) {
            return new \WP_REST_Response(
                ['code' => 'not_found', 'message' => __('البائع غير موجود', 'vmp')],
                404
            );
        }

        return new \WP_REST_Response($data, 200);
    }

    /**
     * GET /vmp/v1/vendors/{id}/products — منتجات بائع محدد
     */
    public function getVendorProducts(\WP_REST_Request $request): \WP_REST_Response
    {
        $vendorId = (int) $request->get_param('id');
        $limit    = (int) $request->get_param('limit');
        $offset   = (int) $request->get_param('offset');

        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor || $vendor->status !== 'approved') {
            return new \WP_REST_Response(
                ['code' => 'not_found', 'message' => __('البائع غير موجود', 'vmp')],
                404
            );
        }

        $cacheKey = CacheManager::listKey('vendor_products_' . $vendorId, ['limit' => $limit, 'offset' => $offset]);
        $data = $this->cache->remember($cacheKey, 300, function () use ($vendorId, $limit, $offset) {
            $products = $this->productRepository->getByVendor($vendorId, [
                'status' => 'approved',
                'limit'  => $limit,
                'offset' => $offset,
            ]);

            $result = [];
            foreach ($products as $product) {
                $wcProduct = wc_get_product($product->product_id);
                if (!$wcProduct) {
                    continue;
                }

                $result[] = [
                    'id'            => (int) $product->id,
                    'product_id'    => (int) $product->product_id,
                    'name'          => $wcProduct->get_name(),
                    'price'         => $wcProduct->get_price(),
                    'regular_price' => $wcProduct->get_regular_price(),
                    'sale_price'    => $wcProduct->get_sale_price(),
                    'image'         => wp_get_attachment_url($wcProduct->get_image_id()) ?: '',
                    'permalink'     => $wcProduct->get_permalink(),
                    'rating'        => $wcProduct->get_average_rating(),
                    'stock_status'  => $wcProduct->get_stock_status(),
                ];
            }

            return $result;
        });

        return new \WP_REST_Response([
            'data'      => $data,
            'vendor_id' => $vendorId,
            'total'     => count($data),
            'limit'     => $limit,
            'offset'    => $offset,
        ], 200);
    }

    /**
     * GET /vmp/v1/vendors/me — بيانات البائع الحالي (يتطلب مصادقة)
     */
    public function getCurrentVendor(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId   = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            return new \WP_REST_Response(
                ['code' => 'not_found', 'message' => __('البائع غير موجود', 'vmp')],
                404
            );
        }

        return new \WP_REST_Response([
            'id'              => (int) $vendor->id,
            'store_name'      => $vendor->store_name,
            'store_slug'      => $vendor->store_slug,
            'store_email'     => $vendor->store_email,
            'store_phone'     => $vendor->store_phone,
            'store_address'   => $vendor->store_address,
            'status'          => $vendor->status,
            'rating'          => (float) $vendor->rating,
            'review_count'    => (int) $vendor->review_count,
            'balance'         => (float) $vendor->balance,
            'total_sales'     => (float) $vendor->total_sales,
            'total_products'  => (int) $vendor->total_products,
            'total_orders'    => (int) $vendor->total_orders,
            'is_trusted'      => (bool) $vendor->is_trusted,
        ], 200);
    }
}
