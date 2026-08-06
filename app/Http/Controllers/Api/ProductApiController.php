<?php
/**
 * ProductApiController — REST API لإدارة المنتجات
 *
 * Namespace: /wp-json/vmp/v1/
 *
 * Endpoints:
 *  GET  /products/{id}  — تفاصيل منتج (عام)
 *  GET  /products       — منتجات البائع الحالي (مصادق)
 *
 * @package VMP\Http\Controllers\Api
 * @since 3.0.0
 */

namespace VMP\Http\Controllers\Api;

defined('ABSPATH') || exit;

use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Support\Cache\Manager as CacheManager;
use VMP\Http\Resources\ProductResource;
use VMP\Http\Controllers\Api\Traits\VendorAuthHelpers;

class ProductApiController
{
    private const NAMESPACE = 'vmp/v1';

    use VendorAuthHelpers;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private VendorRepositoryInterface  $vendorRepository
    ) {}

    /**
     * تسجيل مسارات REST API
     */
    public function registerRoutes(): void
    {
        // ─── Public: تفاصيل منتج ────────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/products/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        // ─── Auth: منتجات البائع الحالي ─────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/products', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'requiresVendor'],
            'args'                => [
                'per_page' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                'page'     => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                'status'   => ['type' => 'string', 'default' => ''],
            ],
        ]);
    }

    // ─── Permission Callbacks ────────────────────────────────────────────────

    // ─── Handlers ────────────────────────────────────────────────────────────

    /**
     * منتجات البائع الحالي
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function index(\WP_REST_Request $request)
    {
        $vendor = $request->get_param('__vendor');
        if (!$vendor) {
            return new \WP_Error(
                'not_found',
                __('لم يُعثر على بيانات البائع.', 'vmp'),
                ['status' => 404]
            );
        }

        $perPage = (int) $request->get_param('per_page');
        $page    = (int) $request->get_param('page');
        $status  = sanitize_key($request->get_param('status'));
        $offset  = ($page - 1) * $perPage;

        $args = ['limit' => $perPage, 'offset' => $offset];
        if ($status) {
            $args['status'] = $status;
        }

        $products = $this->productRepository->findByVendor($vendor->id, $args);
        $total    = $this->productRepository->countByVendor($vendor->id, $status ?: null);

        $data = array_map([$this, 'formatProductForApi'], $products);

        $response = new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => (int) $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ], 200);

        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) ceil($total / $perPage));

        return $response;
    }

    /**
     * تفاصيل منتج محدد
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function show(\WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $cacheKey = 'api_product_' . $id;
        $data     = CacheManager::get($cacheKey);

        if ($data === false) {
            $product = $this->productRepository->find($id);

            if (!$product || $product->status !== 'approved') {
                return new \WP_Error(
                    'not_found',
                    __('المنتج غير موجود.', 'vmp'),
                    ['status' => 404]
                );
            }

            // [QA 2026-08-06] التحقق من حالة البائع أيضاً — منع عرض منتجات
            // بائع معلّق/محذوف/غير معتمد في API العام (المنتج قد يبقى approved
            // حتى لو حُظر/عُلّق البائع لاحقاً).
            $vendor = $this->vendorRepository->find((int) $product->vendor_id);
            if (!$vendor || $vendor->status !== 'approved') {
                return new \WP_Error(
                    'not_found',
                    __('المنتج غير موجود.', 'vmp'),
                    ['status' => 404]
                );
            }

            // ✅ تخزين array مُنسق بدلاً من object
            $data = ProductResource::toArray($product);
            CacheManager::set($cacheKey, $data, CacheManager::configuredTtl()); // 10 دقائق
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    // ─── Formatters ─────────────────────────────────────────────────────────

    // ─── Cache Invalidation ─────────────────────────────────────────────────

    /**
     * مسح cache منتج محدد
     *
     * @param int $productId
     * @return void
     */
    public static function clearProductCache(int $productId): void
    {
        CacheManager::delete('api_product_' . $productId);
    }
}
