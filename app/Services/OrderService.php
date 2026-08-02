<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\OrderRepositoryInterface;
use VMP\Contracts\CommissionRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\ProductRepositoryInterface;
use VMP\Core\EventManager;
use VMP\Core\Logger;
use VMP\Support\Transaction;
use Exception;

/**
 * Class OrderService
 *
 * Description of administrative platform component OrderService.
 *
 * @package vendor-marketplace
 */
class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private CommissionRepositoryInterface $commissionRepository,
        private VendorRepositoryInterface $vendorRepository,
        private ProductRepositoryInterface $productRepository,
        private CommissionService $commissionService,
        private EventManager $eventManager,
        private Logger $logger
    ) {}

    /**
     * تقسيم الطلب الرئيسي إلى طلبات فرعية لكل بائع
     *
     * @param \WC_Order $order
     * @return void
     */
    public function splitOrder(\WC_Order $order): void
    {
        $parentOrderId = $order->get_id();
        $items = $order->get_items();

        $vendorItems = [];

        foreach ($items as $item) {
            $productId = $item->get_product_id();
            if (!$productId) {
                continue;
            }

            $vendorProduct = $this->productRepository->findByProductId($productId);
            if (!$vendorProduct) {
                continue;
            }

            $vendorId = (int) $vendorProduct->vendor_id;
            if (!isset($vendorItems[$vendorId])) {
                $vendorItems[$vendorId] = ['items' => [], 'total' => 0.0];
            }

            $vendorItems[$vendorId]['items'][] = $item;
            $vendorItems[$vendorId]['total'] += (float) $item->get_total();
        }

        foreach ($vendorItems as $vendorId => $data) {
            $this->createVendorOrder($vendorId, $parentOrderId, $data);
        }
    }

    /**
     * إنشاء طلب فرعي لبائع
     *
     * @param int $vendorId
     * @param int $parentOrderId
     * @param array $data
     * @return void
     */
    private function createVendorOrder(int $vendorId, int $parentOrderId, array $data): void
    {
        $transaction = new Transaction();
        $transaction->begin();

        try {
            $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor || $vendor->status !== 'approved') {
            return;
        }

        $commissionRate = $this->commissionService->calculateRate($vendorId);
        $total = (float) $data['total'];
        $calc = $this->commissionService->calculateAmount($total, $commissionRate);

        $vendorOrderId = $this->orderRepository->create([
            'vendor_id' => $vendorId,
            'order_id' => $parentOrderId,
            'parent_order_id' => $parentOrderId,
            'status' => 'pending',
            'total' => $total,
            'commission' => $calc['commission_amount'],
            'vendor_earnings' => $calc['vendor_amount'],
        ]);

        if (!$vendorOrderId) {
            $this->logger->error('فشل إنشاء الطلب الفرعي', [
                'vendor_id' => $vendorId,
                'parent_order_id' => $parentOrderId,
            ]);
            return;
        }

        foreach ($data['items'] as $item) {
            $itemTotal = (float) $item->get_total();
            $itemCalc = $this->commissionService->calculateAmount($itemTotal, $commissionRate);

            $this->commissionRepository->create([
                'vendor_id' => $vendorId,
                'order_id' => $parentOrderId,
                'vendor_order_id' => $vendorOrderId,
                'product_id' => $item->get_product_id(),
                'amount' => $itemTotal,
                'commission_rate' => $commissionRate,
                'commission_amount' => $itemCalc['commission_amount'],
                'vendor_amount' => $itemCalc['vendor_amount'],
                'status' => 'pending',
            ]);
        }

        $this->logger->info('تم إنشاء طلب فرعي', [
            'vendor_order_id' => $vendorOrderId,
            'vendor_id' => $vendorId,
            'total' => $total,
            'commission_rate' => $commissionRate,
        ]);

        try {
            $this->eventManager->trigger(
                'vmp_order_placed', $vendorOrderId, $parentOrderId, $vendorId
            );
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث إنشاء الطلب الفرعي: ' . $e->getMessage());
        }

        $transaction->commit();
        } catch (Throwable $e) {
            // تراجع عن المعاملة وإعادة الرمي للمعالج العلوي
            try {
                $transaction->rollback();
            } catch (Throwable $rollbackError) {
                $this->logger->error('فشل التراجع عن المعاملة: ' . $rollbackError->getMessage());
            }
            $this->logger->error('فشل إنشاء الطلب الفرعي: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * اكتمال طلب (وتحديث رصيد البائع)
     *
     * @param int $orderId
     * @return void
     */
    public function completeOrder(int $orderId): void
    {
        $vendorOrders = $this->orderRepository->getByParentOrder($orderId);

        foreach ($vendorOrders as $vendorOrder) {
            $this->orderRepository->update((int) $vendorOrder->id, ['status' => 'completed']);
            $this->vendorRepository->updateBalance(
                (int) $vendorOrder->vendor_id,
                (float) $vendorOrder->vendor_earnings
            );

            // تم نقل دالة updateStats إلى VendorService لكن يمكن استدعاؤها عبر Event لاحقاً أو هنا مباشرة إذا تم حقن VendorService
            // للاختصار سيتم تحديث الإحصائيات في Event أو عند الطلب.
            
            $this->logger->info('اكتمل طلب البائع وتم تحديث رصيده', [
                'vendor_order_id' => $vendorOrder->id,
                'vendor_id' => $vendorOrder->vendor_id,
                'earnings' => $vendorOrder->vendor_earnings,
            ]);

            try {
                $this->eventManager->trigger(
                    'vmp_order_completed',
                    (int) $vendorOrder->id, $orderId, (int) $vendorOrder->vendor_id
                );
            } catch (Exception $e) {
                $this->logger->error('فشل إطلاق حدث اكتمال الطلب: ' . $e->getMessage());
            }
        }
    }

    /**
     * إلغاء طلب (واسترجاع الرصيد إذا كان مكتمل)
     *
     * @param int $orderId
     * @return void
     */
    public function cancelOrder(int $orderId): void
    {
        $vendorOrders = $this->orderRepository->getByParentOrder($orderId);

        foreach ($vendorOrders as $vendorOrder) {
            if ($vendorOrder->status === 'cancelled') {
                continue;
            }

            if ($vendorOrder->status === 'completed') {
                $this->vendorRepository->updateBalance(
                    (int) $vendorOrder->vendor_id,
                    -(float) $vendorOrder->vendor_earnings
                );
            }

            $this->orderRepository->update((int) $vendorOrder->id, ['status' => 'cancelled']);

            try {
                $this->eventManager->trigger(
                    'vmp_order_cancelled',
                    (int) $vendorOrder->id, $orderId, (int) $vendorOrder->vendor_id
                );
            } catch (Exception $e) {
                $this->logger->error('فشل إطلاق حدث إلغاء الطلب: ' . $e->getMessage());
            }
        }
    }

    /**
     * جلب طلبات بائع معين (مع إثراء البيانات)
     *
     * @param int $vendorId
     * @param array $args
     * @return array
     */
    public function getVendorOrders(int $vendorId, array $args = []): array
    {
        $orders = $this->orderRepository->getByVendor($vendorId, $args);
        $enriched = [];

        foreach ($orders as $order) {
            $wcOrder = wc_get_order($order->order_id);
            $enriched[] = [
                'id'              => (int) $order->id,
                'order_id'        => (int) $order->order_id,
                'order_number'    => $wcOrder ? $wcOrder->get_order_number() : $order->order_id,
                'status'          => $order->status,
                'total'           => (float) $order->total,
                'vendor_earnings' => (float) $order->vendor_earnings,
                'commission'      => (float) $order->commission,
                'customer_name'   => $wcOrder ? $wcOrder->get_billing_first_name() . ' ' . $wcOrder->get_billing_last_name() : '',
                'created_at'      => $order->created_at,
            ];
        }

        return $enriched;
    }

    /**
     * جلب تفاصيل طلب فرعي
     *
     * @param int $vendorOrderId
     * @return array
     * @throws Exception
     */
    public function getOrderDetails(int $vendorOrderId): array
    {
        $vendorOrder = $this->orderRepository->find($vendorOrderId);
        if (!$vendorOrder) {
            throw new Exception(__('الطلب غير موجود', 'vmp'));
        }

        // ✅ التحقق من ملكية البائع (دفاع إضافي في العمق)
        //    الأدمن مسموح، البائع يجب أن يملك الطلب
        $isAdmin = function_exists('current_user_can') && current_user_can('vmp_manage_orders');
        if (!$isAdmin) {
            $userId = get_current_user_id();
            $currentVendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);
            if (!$currentVendorId) {
                global $wpdb;
                $currentVendorId = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
                    $userId
                ));
            }
            if ((int) $vendorOrder->vendor_id !== $currentVendorId) {
                throw new Exception(__('لا تملك صلاحية الوصول إلى هذا الطلب', 'vmp'));
            }
        }

        $wcOrder = function_exists('wc_get_order') ? wc_get_order($vendorOrder->order_id) : null;

        return [
            'vendor_order'   => $vendorOrder,
            'order_number'   => $wcOrder ? $wcOrder->get_order_number() : '',
            'customer_name'  => $wcOrder ? $wcOrder->get_billing_first_name() . ' ' . $wcOrder->get_billing_last_name() : '',
            'customer_email' => $wcOrder ? $wcOrder->get_billing_email() : '',
            'order_date'     => $wcOrder && $wcOrder->get_date_created() ? $wcOrder->get_date_created()->date('Y-m-d H:i') : '',
        ];
    }
}
