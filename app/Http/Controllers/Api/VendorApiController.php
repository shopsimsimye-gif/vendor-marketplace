<?php
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
 *
 * @package VMP\Http\Controllers\Api
 * @since 3.0.0
 */

namespace VMP\Http\Controllers\Api;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\OrderRepositoryInterface;
use VMP\Services\VendorService;
use VMP\Support\Cache\Manager as CacheManager;
use VMP\Http\Resources\VendorResource;

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
                'page'     => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
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
                'per_page' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                'page'     => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
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
     * التحقق من أن المستخدم بائع معتمد
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function requiresVendor(\WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'unauthorized',
                __('يجب تسجيل الدخول أولاً.', 'vmp'),
                ['status' => 401]
            );
        }

        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());

        if (!$vendor || $vendor->status !== 'approved') {
            return new \WP_Error(
                'forbidden',
                __('يجب أن تكون بائعاً معتمداً.', 'vmp'),
                ['status' => 403]
            );
        }

        // ✅ تخزين البائع في الطلب لتجنب تكرار الـ DB query
        $request->set_param('__vendor', $vendor);

        return true;
    }

    // ─── Handlers ────────────────────────────────────────────────────────────

    /**
     * قائمة البائعين المعتمدين
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function index(\WP_REST_Request $request)
    {
        $perPage = (int) $request->get_param('per_page');
        $page    = (int) $request->get_param('page');
        $search  = sanitize_text_field($request->get_param('search'));
        $orderBy = sanitize_key($request->get_param('order_by'));
        $offset  = ($page - 1) * $perPage;

        $cacheKey = 'api_vendors_' . md5($search . $perPage . $offset . $orderBy);
        $data     = CacheManager::get($cacheKey);

        if ($data === false) {
            $vendors = $this->vendorRepository->findAll([
                'status'  => 'approved',
                'search'  => $search,
                'limit'   => $perPage,
                'offset'  => $offset,
                'orderby' => $orderBy,
            ]);

            // ✅ تخزين array مُنسق بدلاً من objects
            $data = array_map(
                static fn($vendor) => VendorResource::toArray($vendor, false),
                $vendors
            );

            CacheManager::set($cacheKey, $data, CacheManager::configuredTtl()); // 5 دقائق
        }

        // ✅ إجمالي العدد للـ pagination
        $total = $this->vendorRepository->count(['status' => 'approved', 'search' => $search]);

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

        // ✅ إضافة headers للـ pagination (متوافق مع WP REST API)
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) ceil($total / $perPage));

        return $response;
    }

    /**
     * بيانات بائع محدد
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function show(\WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $cacheKey = 'api_vendor_' . $id;
        $data     = CacheManager::get($cacheKey);

        if ($data === false) {
            $vendor = $this->vendorRepository->find($id);

            if (!$vendor || $vendor->status !== 'approved') {
                return new \WP_Error(
                    'not_found',
                    __('البائع غير موجود.', 'vmp'),
                    ['status' => 404]
                );
            }

            $data = VendorResource::toArray($vendor, false);
            CacheManager::set($cacheKey, $data, CacheManager::configuredTtl()); // 10 دقائق
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * منتجات بائع محدد
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function products(\WP_REST_Request $request)
    {
        $vendorId = (int) $request->get_param('id');
        $perPage  = (int) $request->get_param('per_page');
        $page     = (int) $request->get_param('page');
        $offset   = ($page - 1) * $perPage;

        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor || $vendor->status !== 'approved') {
            return new \WP_Error(
                'not_found',
                __('البائع غير موجود.', 'vmp'),
                ['status' => 404]
            );
        }

        $products = $this->productRepository->findByVendor($vendorId, [
            'status' => 'approved',
            'limit'  => $perPage,
            'offset' => $offset,
        ]);

        $total = $this->productRepository->countByVendor($vendorId, 'approved');

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
     * بيانات البائع الحالي
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function me(\WP_REST_Request $request)
    {
        // ✅ استخدام البائع المُخزن في الطلب (من requiresVendor)
        $vendor = $request->get_param('__vendor');

        if (!$vendor) {
            return new \WP_Error(
                'not_found',
                __('لم يُعثر على بيانات البائع.', 'vmp'),
                ['status' => 404]
            );
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => VendorResource::toArray($vendor, true),
        ], 200);
    }

    /**
     * طلبات البائع الحالي
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function myOrders(\WP_REST_Request $request)
    {
        $vendor  = $request->get_param('__vendor');
        if (!$vendor) {
            return new \WP_Error('not_found', __('لم يُعثر على بيانات البائع.', 'vmp'), ['status' => 404]);
        }

        $perPage = (int) $request->get_param('per_page');
        $page    = (int) $request->get_param('page');
        $status  = sanitize_key($request->get_param('status'));
        $offset  = ($page - 1) * $perPage;

        $args = ['limit' => $perPage, 'offset' => $offset];
        if ($status) {
            $args['status'] = $status;
        }

        $orders = $this->orderRepository->findByVendor($vendor->id, $args);
        $total  = $this->orderRepository->countByVendor($vendor->id, $status ?: null);

        $data = array_map([$this, 'formatOrderForApi'], $orders);

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
     * إحصائيات البائع الحالي
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function myStats(\WP_REST_Request $request)
    {
        $vendor = $request->get_param('__vendor');
        if (!$vendor) {
            return new \WP_Error('not_found', __('لم يُعثر على بيانات البائع.', 'vmp'), ['status' => 404]);
        }

        $stats = $this->vendorService->getVendorStats($vendor->id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $stats,
        ], 200);
    }

    // ─── Formatters ─────────────────────────────────────────────────────────

    /**
     * تنسيق منتج للـ API
     *
     * @param object $product
     * @return array
     */
    private function formatProductForApi(object $product): array
    {
        $productId = (int) ($product->product_id ?? $product->id ?? 0);

        // ✅ استخدام wc_get_product بدلاً من get_the_title (أكثر أماناً في REST context)
        $wcProduct = $productId > 0 ? wc_get_product($productId) : null;
        $title     = $wcProduct ? $wcProduct->get_name() : ($product->title ?? '');

        $price = (float) ($product->price ?? 0);

        return [
            'id'           => $productId,
            'title'        => (string) $title,
            'price'        => $price,
            'price_html'   => function_exists('wc_price') ? wc_price($price) : null,
            'status'       => (string) ($product->status ?? ''),
            'stock_status' => (string) ($product->stock_status ?? 'instock'),
            'image_url'    => $this->getAttachmentUrl(
                (int) ($wcProduct ? $wcProduct->get_image_id() : get_post_thumbnail_id($productId)),
                'woocommerce_thumbnail'
            ),
        ];
    }

    /**
     * تنسيق طلب للـ API
     *
     * @param object $order
     * @return array
     */
    private function formatOrderForApi(object $order): array
    {
        return [
            'id'              => (int) ($order->id ?? 0),
            'parent_order_id' => (int) ($order->parent_order_id ?? 0),
            'status'          => (string) ($order->status ?? ''),
            'total'           => (float) ($order->total ?? 0),
            'vendor_earnings' => (float) ($order->vendor_earnings ?? 0),
            'created_at'      => !empty($order->created_at)
                ? date('c', strtotime((string) $order->created_at))
                : null,
        ];
    }

    /**
     * الحصول على رابط مرفق
     *
     * @param int    $attachmentId
     * @param string $size
     * @return string
     */
    private function getAttachmentUrl(int $attachmentId, string $size = 'thumbnail'): string
    {
        if ($attachmentId <= 0) {
            return '';
        }
        $url = wp_get_attachment_image_url($attachmentId, $size);
        return $url ? (string) $url : '';
    }

    // ─── Cache Invalidation Helpers ─────────────────────────────────────────

    /**
     * مسح cache بائع محدد
     *
     * @param int $vendorId
     * @return void
     */
    public static function clearVendorCache(int $vendorId): void
    {
        CacheManager::delete('api_vendor_' . $vendorId);
        CacheManager::delete('api_vendors_'); // wildcard delete إن كان مدعوماً
    }

    /**
     * مسح cache قائمة البائعين
     *
     * @return void
     */
    public static function clearVendorsListCache(): void
    {
        // ✅ يفترض أن CacheManager يدعم delete by pattern أو prefix
        // إذا لم يكن مدعوماً، استخدم transient keys معروفة
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_api_vendors_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_api_vendors_%'"
        );
    }
}
