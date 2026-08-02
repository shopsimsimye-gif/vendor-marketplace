<?php
namespace VMP\Http\Controllers\Api;

defined('ABSPATH') || exit;

use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Support\Cache\Manager as CacheManager;
use VMP\Http\Resources\ProductResource;

/**
 * ProductApiController — REST API لإدارة المنتجات
 *
 * Namespace: /wp-json/vmp/v1/
 */
class ProductApiController
{
    private const NAMESPACE = 'vmp/v1';

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private VendorRepositoryInterface  $vendorRepository
    ) {}

    /**
     * RegisterRoutes functionality helper.
     *
     * @return void Output payload.
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
        
        // ─── Auth: منتجات البائع ────────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/products', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'requiresVendor'],
            'args'                => [
                'per_page' => ['type' => 'integer', 'default' => 20],
                'page'     => ['type' => 'integer', 'default' => 1],
                'status'   => ['type' => 'string', 'default' => ''],
            ],
        ]);
    }

    /**
     * RequiresVendor functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return bool| Output payload.
     */
    public function requiresVendor(\WP_REST_Request $request): bool|\WP_Error
    {
        if (!is_user_logged_in()) {
            return new \WP_Error('unauthorized', __('يجب تسجيل الدخول أولاً.', 'vmp'), ['status' => 401]);
        }

        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor || $vendor->status !== 'approved') {
            return new \WP_Error('forbidden', __('يجب أن تكون بائعاً معتمداً.', 'vmp'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Index functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $vendor  = $this->vendorRepository->findByUserId(get_current_user_id());
        $perPage = (int) $request->get_param('per_page');
        $page    = (int) $request->get_param('page');
        $status  = sanitize_key($request->get_param('status'));
        $offset  = ($page - 1) * $perPage;

        $args = ['limit' => $perPage, 'offset' => $offset];
        if ($status) $args['status'] = $status;

        $products = $this->productRepository->findByVendor($vendor->id, $args);
        $data     = array_map([$this, 'formatProductForApi'], $products);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => ['page' => $page, 'per_page' => $perPage, 'count' => count($data)],
        ], 200);
    }

    /**
     * Show functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        
        $cacheKey = 'api_product_' . $id;
        $product  = CacheManager::get($cacheKey);

        if ($product === false) {
            $product = $this->productRepository->find($id);
            if ($product) {
                CacheManager::set($cacheKey, $product, 600);
            }
        }

        if (!$product || $product->status !== 'approved') {
            return new \WP_Error('not_found', __('المنتج غير موجود.', 'vmp'), ['status' => 404]);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => ProductResource::toArray($product),
        ], 200);
    }

    /**
     * FormatProductForApi functionality helper.
     *
     * @param object $product Description index.
     * @return array Output payload.
     */
    private function formatProductForApi(object $product): array
    {
        return [
            'id'            => (int) ($product->product_id ?? $product->id ?? 0),
            'title'         => esc_html(get_the_title($product->product_id ?? 0) ?: ($product->title ?? '')),
            'price'         => function_exists('wc_price') ? wc_price((float) ($product->price ?? 0)) : (float) ($product->price ?? 0),
            'price_raw'     => (float) ($product->price ?? 0),
            'status'        => esc_attr($product->status ?? ''),
            'stock_status'  => esc_attr($product->stock_status ?? 'instock'),
            'image_url'     => $this->getAttachmentUrl((int) (get_post_thumbnail_id($product->product_id ?? 0)), 'woocommerce_thumbnail'),
        ];
    }

    /**
     * GetAttachmentUrl functionality helper.
     *
     * @param int $attachmentId Description index.
     * @param string $size Description index.
     * @return string Output payload.
     */
    private function getAttachmentUrl(int $attachmentId, string $size = 'thumbnail'): string
    {
        if ($attachmentId <= 0) return '';
        $url = wp_get_attachment_image_url($attachmentId, $size);
        return $url ? esc_url($url) : '';
    }
}
