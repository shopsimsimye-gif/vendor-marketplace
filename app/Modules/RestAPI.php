<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\ProductRepository;
use VMP\Repositories\VendorRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RestAPI
 *
 * Description of administrative platform component RestAPI.
 *
 * @package vendor-marketplace
 */
class RestAPI extends AbstractModule
{
    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register Routes functionality helper.
     *
     * @return void Output payload.
     */
    public function register_routes(): void
    {
        register_rest_route('vmp/v1', '/vendors', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vendors'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('vmp/v1', '/vendors/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vendor'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('vmp/v1', '/vendors/(?P<id>\d+)/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vendor_products'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get Vendors functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function get_vendors(\WP_REST_Request $request): \WP_REST_Response
    {
        $vendor_repo = $this->make(VendorRepository::class);
        $vendors = $vendor_repo->getAll(['status' => 'approved', 'limit' => 50]);
        $data = [];

        foreach ($vendors as $vendor) {
            $data[] = [
                'id' => (int) $vendor->id,
                'store_name' => $vendor->store_name,
                'store_slug' => $vendor->store_slug,
                'store_description' => $vendor->store_description,
                'rating' => (float) $vendor->rating,
                'is_trusted' => (bool) $vendor->is_trusted,
            ];
        }

        return new \WP_REST_Response($data, 200);
    }

    /**
     * Get Vendor functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function get_vendor(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $vendor_repo = $this->make(VendorRepository::class);
        $vendor = $vendor_repo->find($id);

        if (!$vendor) {
            return new \WP_REST_Response(['error' => 'Vendor not found'], 404);
        }

        return new \WP_REST_Response([
            'id' => (int) $vendor->id,
            'store_name' => $vendor->store_name,
            'store_slug' => $vendor->store_slug,
            'store_description' => $vendor->store_description,
            'store_address' => $vendor->store_address,
            'store_phone' => $vendor->store_phone,
            'store_email' => $vendor->store_email,
            'rating' => (float) $vendor->rating,
            'review_count' => (int) $vendor->review_count,
            'is_trusted' => (bool) $vendor->is_trusted,
            'total_products' => (int) $vendor->total_products,
            'total_orders' => (int) $vendor->total_orders,
        ], 200);
    }

    /**
     * Get Vendor Products functionality helper.
     *
     * @param \WP_REST_Request $request Description index.
     * @return mixed Output payload.
     */
    public function get_vendor_products(\WP_REST_Request $request): \WP_REST_Response
    {
        $vendor_id = (int) $request->get_param('id');
        $product_repo = $this->make(ProductRepository::class);
        $products = $product_repo->getByVendor($vendor_id, ['status' => 'approved', 'limit' => 50]);
        $data = [];

        foreach ($products as $product) {
            $wc_product = \wc_get_product($product->product_id);
            if (!$wc_product) {
                continue;
            }

            $data[] = [
                'id' => (int) $product->id,
                'product_id' => (int) $product->product_id,
                'name' => $wc_product->get_name(),
                'price' => $wc_product->get_price(),
                'regular_price' => $wc_product->get_regular_price(),
                'sale_price' => $wc_product->get_sale_price(),
                'image' => \wp_get_attachment_url($wc_product->get_image_id()),
                'permalink' => $wc_product->get_permalink(),
            ];
        }

        return new \WP_REST_Response($data, 200);
    }
}
