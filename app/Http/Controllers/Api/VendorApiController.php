<?php
namespace VMP\Http\Controllers\Api;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\OrderRepositoryInterface;
use VMP\Services\VendorService;
use VMP\Http\Responses\JsonResponse;
use VMP\Http\Middleware\RateLimitMiddleware;
use VMP\Support\Cache\Manager as CacheManager;
use VMP\Http\Resources\VendorResource;
use VMP\Http\Resources\OrderResource;

/**
 * VendorApiController — REST API لإدارة البائعين
 *
 * Namespace: /wp-json/vmp/v1/
 *
 * Endpoints:
 *  GET  /vendors                  — قائمة البائعين المعتمدين (عام)
 *  GET  /vendors/{id}             — بيانات بائع (عام)
 *  GET  /vendors/{id}/products    — منتجات بائع (عام)
 *  GET  /vendors/me               — بيانات البائع الحالي (مصادق)
 *  GET  /vendors/me/orders        — طلبات البائع الحالي (مصادق)
 *  GET  /vendors/me/stats         — إحصائيات البائع الحالي (مصادق)
 */
class VendorApiController
{
    private const NAMESPACE = 'vmp/v1';

    public function __construct(
        private VendorRepositoryInterface  $vendorRepository,
        private ProductRepositoryInterface $productRepository,
        private OrderRepositoryInterface   $orderRepository,
        private VendorService              $vendorService
    ) {}

    /**
     * تسجيل مسارات REST API
     */
    public function registerRoutes(): void
    {
        // ─── Public: قائمة البائعين ──────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/vendors', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
            'args'                => [
                'per_page' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                'page'     => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                'search'   => ['type' => 'string', 'default' => ''],
                'order_by' => ['type' => 'string', 'default' => 'store_name', 'enum' => ['store_name', 'rating', 'total_sales']],
            ],
        ]);

        // ─── Public: بائع محدد ──────────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/vendors/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        // ─── Public: منتجات بائع ────────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/vendors/(?P<id>\d+)/products', [
            'methods'             => 'GET',
            'callback'            => [$this, 'products'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id'       => ['type' => 'integer', 'required' => true],
                'per_page' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                'page'     => ['type' => 'integer', 'default' => 1],
            ],
        ]);

        // ─── Auth: البائع الحالي ─────────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/vendors/me', [
            'methods'             => 'GET',
            'callback'            => [$this, 'me'],
            'permission_callback' => [$this, 'requiresVendor'],
        ]);

        // ─── Auth: طلبات البائع الحالي ──────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/vendors/me/orders', [
            'methods'             => 'GET',
            'callback'            => [$this, 'myOrders'],
            'permission_callback' => [$this, 'requiresVendor'],
            'args'                => [
                'per_page' => ['type' => 'integer', 'default' => 20],
                'page'     => ['type' => 'integer', 'default' => 1],
                'status'   => ['type' => 'string', 'default' => ''],
            ],
        ]);

        // ─── Auth: إحصائيات البائع ──────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/vendors/me/stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'myStats'],
            'permission_callback' => [$this, 'requiresVendor'],
        ]);
    }

    // ─── Permission Callbacks ────────────────────────────────────────────────

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

    // ─── Handlers ────────────────────────────────────────────────────────────

    /**
     * Index functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $perPage = (int) $request->get_param('per_page');
        $page    = (int) $request->get_param('page');
        $search  = sanitize_text_field($request->get_param('search'));
        $orderBy = sanitize_key($request->get_param('order_by'));

        $offset  = ($page - 1) * $perPage;

        $cacheKey = 'api_vendors_' . md5($search . $perPage . $offset . $orderBy);
        $vendors  = CacheManager::get($cacheKey);

        if ($vendors === false) {
            $vendors = $this->vendorRepository->findAll([
                'status'  => 'approved',
                'search'  => $search,
                'limit'   => $perPage,
                'offset'  => $offset,
                'orderby' => $orderBy,
            ]);
            CacheManager::set($cacheKey, $vendors, 300); // 5 دقائق
        }

        $data = array_map([$this, 'formatVendorForApi'], $vendors);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'page'     => $page,
                'per_page' => $perPage,
                'count'    => count($data),
            ],
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

        $cacheKey = 'api_vendor_' . $id;
        $vendor   = CacheManager::get($cacheKey);

        if ($vendor === false) {
            $vendor = $this->vendorRepository->find($id);
            if ($vendor) {
                CacheManager::set($cacheKey, $vendor, 600);
            }
        }

        if (!$vendor || $vendor->status !== 'approved') {
            return new \WP_Error('not_found', __('البائع غير موجود.', 'vmp'), ['status' => 404]);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => VendorResource::toArray($vendor, false),
        ], 200);
    }

    /**
     * Products functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function products(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $vendorId = (int) $request->get_param('id');
        $perPage  = (int) $request->get_param('per_page');
        $page     = (int) $request->get_param('page');
        $offset   = ($page - 1) * $perPage;

        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor || $vendor->status !== 'approved') {
            return new \WP_Error('not_found', __('البائع غير موجود.', 'vmp'), ['status' => 404]);
        }

        $products = $this->productRepository->findByVendor($vendorId, [
            'status' => 'approved',
            'limit'  => $perPage,
            'offset' => $offset,
        ]);

        $data = array_map([$this, 'formatProductForApi'], $products);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => ['page' => $page, 'per_page' => $perPage, 'count' => count($data)],
        ], 200);
    }

    /**
     * Me functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function me(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            return new \WP_Error('not_found', __('لم يُعثر على بيانات البائع.', 'vmp'), ['status' => 404]);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => VendorResource::toArray($vendor, true),
        ], 200);
    }

    /**
     * MyOrders functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function myOrders(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $vendor  = $this->vendorRepository->findByUserId(get_current_user_id());
        $perPage = (int) $request->get_param('per_page');
        $page    = (int) $request->get_param('page');
        $status  = sanitize_key($request->get_param('status'));
        $offset  = ($page - 1) * $perPage;

        $args = ['limit' => $perPage, 'offset' => $offset];
        if ($status) $args['status'] = $status;

        $orders = $this->orderRepository->findByVendor($vendor->id, $args);
        $data   = array_map([$this, 'formatOrderForApi'], $orders);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => ['page' => $page, 'per_page' => $perPage, 'count' => count($data)],
        ], 200);
    }

    /**
     * MyStats functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function myStats(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        $stats  = $this->vendorService->getVendorStats($vendor->id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $stats,
        ], 200);
    }

    // ─── Formatters ─────────────────────────────────────────────────────────

    /**
     * FormatVendorForApi functionality helper.
     *
     * @param object $vendor Description index.
     * @param bool $includePrivate Description index.
     * @return array Output payload.
     */
    private function formatVendorForApi(object $vendor, bool $includePrivate = false): array
    {
        $data = [
            'id'          => (int) $vendor->id,
            'store_name'  => esc_html($vendor->store_name ?? ''),
            'store_slug'  => esc_attr($vendor->store_slug ?? ''),
            'description' => wp_kses_post($vendor->store_description ?? ''),
            'store_url'   => esc_url(home_url('/store/' . ($vendor->store_slug ?? ''))),
            'logo_url'    => $this->getAttachmentUrl((int) ($vendor->store_logo ?? 0), 'thumbnail'),
            'rating'      => (float) ($vendor->rating ?? 0),
            'is_trusted'  => !empty($vendor->is_trusted),
        ];

        if ($includePrivate) {
            $data['balance']             = (float) ($vendor->balance ?? 0);
            $data['total_products']      = (int) ($vendor->total_products ?? 0);
            $data['total_orders']        = (int) ($vendor->total_orders ?? 0);
            $data['subscription_plan']   = esc_html($vendor->subscription_plan ?? '');
            $data['subscription_status'] = esc_html($vendor->subscription_status ?? '');
            $data['subscription_expiry'] = $vendor->subscription_expiry ?? null;
        }

        return $data;
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
     * FormatOrderForApi functionality helper.
     *
     * @param object $order Description index.
     * @return array Output payload.
     */
    private function formatOrderForApi(object $order): array
    {
        return [
            'id'              => (int) ($order->id ?? 0),
            'parent_order_id' => (int) ($order->parent_order_id ?? 0),
            'status'          => esc_attr($order->status ?? ''),
            'total'           => (float) ($order->total ?? 0),
            'vendor_earnings' => (float) ($order->vendor_earnings ?? 0),
            'created_at'      => $order->created_at ?? null,
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
