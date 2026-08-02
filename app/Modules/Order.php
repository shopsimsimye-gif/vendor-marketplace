<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\OrderRepository;
use VMP\Repositories\CommissionRepository;
use VMP\Repositories\VendorRepository;
use VMP\Repositories\ProductRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Order
 *
 * Description of administrative platform component Order.
 *
 * @package vendor-marketplace
 */
class Order extends AbstractModule
{
    private OrderRepository $repository;
    private CommissionRepository $commissionRepository;
    private VendorRepository $vendorRepository;

    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->repository = $this->make(OrderRepository::class);
        $this->commissionRepository = $this->make(CommissionRepository::class);
        $this->vendorRepository = $this->make(VendorRepository::class);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        add_action('woocommerce_checkout_order_created', [$this, 'split_order'], 10, 2);
        add_action('woocommerce_order_status_completed', [$this, 'on_order_completed']);
        add_action('woocommerce_order_status_cancelled', [$this, 'on_order_cancelled']);
        add_action('woocommerce_order_status_refunded', [$this, 'on_order_cancelled']);

        // تم نقل تسجيل AJAX إلى RouteRegistry في CoreServiceProvider
        // add_action('wp_ajax_vmp_get_vendor_orders', [$this, 'ajax_get_vendor_orders']);
        // add_action('wp_ajax_vmp_get_order_details', [$this, 'ajax_get_order_details']);
        // add_action('wp_ajax_vmp_vendor_orders', [$this, 'ajax_vendor_orders']);
    }

    /**
     * Split Order functionality helper.
     *
     * @param mixed $order Description index.
     * @param mixed|null $data Description index.
     * @return void Output payload.
     */
    public function split_order($order, $data = null): void
    {
        $parent_order_id = $order->get_id();
        $items = $order->get_items();

        $vendor_items = [];
        $product_repo = $this->make(ProductRepository::class);

        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            if (!$product_id) {
                continue;
            }

            $vendor_product = $product_repo->findByProductId($product_id);
            if (!$vendor_product) {
                continue;
            }

            $vendor_id = (int) $vendor_product->vendor_id;
            if (!isset($vendor_items[$vendor_id])) {
                $vendor_items[$vendor_id] = ['items' => [], 'total' => 0.0];
            }

            $vendor_items[$vendor_id]['items'][] = $item;
            $vendor_items[$vendor_id]['total'] += (float) $item->get_total();
        }

        foreach ($vendor_items as $vendor_id => $vdata) {
            $this->create_vendor_order($vendor_id, $parent_order_id, $vdata);
        }
    }

    /**
     * Create Vendor Order functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $parent_order_id Description index.
     * @param array $data Description index.
     * @return void Output payload.
     */
    private function create_vendor_order(int $vendor_id, int $parent_order_id, array $data): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor || $vendor->status !== 'approved') {
            return;
        }

        $commission_module = $this->container->get('module_manager')->get_module('commission');
        $commission_rate = $commission_module
            ? $commission_module->calculate_rate($vendor_id)
            : (float) get_option('vmp_default_commission', 10);

        $total = (float) $data['total'];
        $calc = $commission_module
            ? $commission_module->calculate_amount($total, $commission_rate)
            : [
                'rate' => $commission_rate,
                'commission_amount' => round(($total * $commission_rate) / 100, 2),
                'vendor_amount' => round($total - (($total * $commission_rate) / 100), 2),
            ];

        $vendor_order_id = $this->repository->create([
            'vendor_id' => $vendor_id,
            'order_id' => $parent_order_id,
            'parent_order_id' => $parent_order_id,
            'status' => 'pending',
            'total' => $total,
            'commission' => $calc['commission_amount'],
            'vendor_earnings' => $calc['vendor_amount'],
        ]);

        if (!$vendor_order_id) {
            $this->container->get('logger')->error('فشل إنشاء الطلب الفرعي', [
                'vendor_id' => $vendor_id,
                'parent_order_id' => $parent_order_id,
            ]);
            return;
        }

        foreach ($data['items'] as $item) {
            $item_total = (float) $item->get_total();
            $item_calc = $commission_module
                ? $commission_module->calculate_amount($item_total, $commission_rate)
                : [
                    'rate' => $commission_rate,
                    'commission_amount' => round(($item_total * $commission_rate) / 100, 2),
                    'vendor_amount' => round($item_total - (($item_total * $commission_rate) / 100), 2),
                ];

            $this->commissionRepository->create([
                'vendor_id' => $vendor_id,
                'order_id' => $parent_order_id,
                'vendor_order_id' => $vendor_order_id,
                'product_id' => $item->get_product_id(),
                'amount' => $item_total,
                'commission_rate' => $commission_rate,
                'commission_amount' => $item_calc['commission_amount'],
                'vendor_amount' => $item_calc['vendor_amount'],
                'status' => 'pending',
            ]);
        }

        $this->container->get('logger')->info('تم إنشاء طلب فرعي', [
            'vendor_order_id' => $vendor_order_id,
            'vendor_id' => $vendor_id,
            'total' => $total,
            'commission_rate' => $commission_rate,
        ]);

        $this->container->get('event_manager')->trigger(
            'vmp_order_placed', $vendor_order_id, $parent_order_id, $vendor_id
        );
    }

    /**
     * On Order Completed functionality helper.
     *
     * @param int $order_id Description index.
     * @return void Output payload.
     */
    public function on_order_completed(int $order_id): void
    {
        $vendor_orders = $this->repository->getByParentOrder($order_id);

        foreach ($vendor_orders as $vendor_order) {
            $this->repository->update((int) $vendor_order->id, ['status' => 'completed']);
            $this->vendorRepository->updateBalance(
                (int) $vendor_order->vendor_id,
                (float) $vendor_order->vendor_earnings
            );
            $this->vendorRepository->updateStats((int) $vendor_order->vendor_id);

            $this->container->get('logger')->info('اكتمل طلب البائع وتم تحديث رصيده', [
                'vendor_order_id' => $vendor_order->id,
                'vendor_id' => $vendor_order->vendor_id,
                'earnings' => $vendor_order->vendor_earnings,
            ]);

            $this->container->get('event_manager')->trigger(
                'vmp_order_completed',
                (int) $vendor_order->id, $order_id, (int) $vendor_order->vendor_id
            );
        }
    }

    /**
     * On Order Cancelled functionality helper.
     *
     * @param int $order_id Description index.
     * @return void Output payload.
     */
    public function on_order_cancelled(int $order_id): void
    {
        $vendor_orders = $this->repository->getByParentOrder($order_id);

        foreach ($vendor_orders as $vendor_order) {
            if ($vendor_order->status === 'cancelled') {
                continue;
            }

            if ($vendor_order->status === 'completed') {
                $this->vendorRepository->updateBalance(
                    (int) $vendor_order->vendor_id,
                    -(float) $vendor_order->vendor_earnings
                );
            }

            $this->repository->update((int) $vendor_order->id, ['status' => 'cancelled']);

            $this->container->get('event_manager')->trigger(
                'vmp_order_cancelled',
                (int) $vendor_order->id, $order_id, (int) $vendor_order->vendor_id
            );
        }
    }

    /**
     * Ajax Get Vendor Orders functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_get_vendor_orders(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_orders')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $vendor_id = (int) ($_POST['vendor_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $limit = (int) ($_POST['limit'] ?? 20);
        $offset = (int) ($_POST['offset'] ?? 0);

        $orders = $this->repository->getByVendor($vendor_id, [
            'status' => $status,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        wp_send_json_success(['orders' => $orders]);
    }

    /**
     * Ajax Get Order Details functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_get_order_details(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_orders')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $vendor_order_id = (int) ($_POST['vendor_order_id'] ?? 0);
        $vendor_order = $this->repository->find($vendor_order_id);
        if (!$vendor_order) {
            wp_send_json_error(['message' => __('الطلب غير موجود', 'vmp')]);
        }

        $wc_order = wc_get_order($vendor_order->order_id);
        $data = [
            'vendor_order' => $vendor_order,
            'order_number' => $wc_order ? $wc_order->get_order_number() : '',
            'customer_name' => $wc_order ? $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name() : '',
            'customer_email' => $wc_order ? $wc_order->get_billing_email() : '',
            'order_date' => $wc_order ? $wc_order->get_date_created()->date('Y-m-d H:i') : '',
        ];

        wp_send_json_success($data);
    }

    /**
     * Ajax Vendor Orders functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_vendor_orders(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');
        if (!current_user_can('vmp_vendor_orders')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $status = sanitize_text_field($_POST['status'] ?? '');
        $limit = (int) ($_POST['limit'] ?? 20);
        $offset = (int) ($_POST['offset'] ?? 0);

        $orders = $this->repository->getByVendor((int) $vendor->id, [
            'status' => $status,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $enriched = [];
        foreach ($orders as $order) {
            $wc_order = wc_get_order($order->order_id);
            $enriched[] = [
                'id' => (int) $order->id,
                'order_id' => (int) $order->order_id,
                'order_number' => $wc_order ? $wc_order->get_order_number() : $order->order_id,
                'status' => $order->status,
                'total' => (float) $order->total,
                'vendor_earnings' => (float) $order->vendor_earnings,
                'commission' => (float) $order->commission,
                'customer_name' => $wc_order ? $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name() : '',
                'created_at' => $order->created_at,
            ];
        }

        wp_send_json_success(['orders' => $enriched]);
    }
}
