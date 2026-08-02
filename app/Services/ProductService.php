<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\EventManager;
use VMP\Core\Logger;
use VMP\DTO\ProductDTO;
use VMP\Exceptions\ServiceException;
use Exception;

class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private VendorRepositoryInterface $vendorRepository,
        private EventManager $eventManager,
        private Logger $logger,
        private \wpdb $db
    ) {}

    /**
     * إضافة منتج بائع جديد
     * ✅ تم إضافة تحقق إضافي من وجود البائع والصلاحية
     * ✅ تم إضافة معالجة أفضل للأخطاء
     */
    public function addProduct(int $vendorId, ProductDTO $dto): int
    {
        // التحقق من البائع
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            throw new ServiceException(__('البائع غير موجود.', 'vmp'));
        }
        if ($vendor->status !== 'approved') {
            throw new ServiceException(__('البائع غير معتمد.', 'vmp'));
        }

        // التحقق من الحد الأقصى للمنتجات
        $currentCount = $this->productRepository->countByVendor($vendorId);
        $maxProducts = $this->getMaxProducts($vendorId);
        if ($maxProducts > 0 && $currentCount >= $maxProducts) {
            throw new ServiceException(__('لقد وصلت للحد الأقصى من المنتجات في خطتك الحالية.', 'vmp'));
        }

        // التحقق من الإعدادات
        $settings = get_option('vmp_settings', []);
        $autoApprove = isset($settings['general']['auto_approve_products']) 
            && $settings['general']['auto_approve_products'] === '1';

        $productStatus = $autoApprove ? 'publish' : 'pending';
        $vendorProductStatus = $autoApprove ? 'approved' : 'pending';

        // بدء المعاملة (Transaction)
        $this->db->query('START TRANSACTION');

        try {
            // إنشاء منتج WooCommerce
            $product = new \WC_Product_Simple();
            $product->set_name(sanitize_text_field($dto->title ?? ''));
            $product->set_regular_price((float) ($dto->regularPrice ?? 0));
            $product->set_sale_price((float) ($dto->salePrice ?? 0));
            $product->set_description(wp_kses_post($dto->description ?? ''));
            $product->set_short_description(wp_kses_post($dto->shortDescription ?? ''));
            $product->set_sku(sanitize_text_field($dto->sku ?? ''));

            // إدارة المخزون
            $manageStock = $dto->stockQuantity > 0 || $dto->stockStatus !== 'instock';
            $product->set_manage_stock($manageStock);
            if ($manageStock) {
                $product->set_stock_quantity($dto->stockQuantity);
            }

            // الفئات والصور
            if (!empty($dto->categoryIds)) {
                $product->set_category_ids($dto->categoryIds);
            }
            if (!empty($dto->imageId)) {
                $product->set_image_id($dto->imageId);
            }
            if (!empty($dto->galleryImageIds)) {
                $product->set_gallery_image_ids($dto->galleryImageIds);
            }

            $product->set_status($productStatus);
            $productId = $product->save();

            if (!$productId) {
                throw new ServiceException(__('فشل إنشاء المنتج في قاعدة البيانات.', 'vmp'));
            }

            // ربط المنتج بالبائع
            $vendorProductId = $this->productRepository->create($vendorId, $productId, [
                'status'      => $vendorProductStatus,
                'is_featured' => !empty($dto->isFeatured),
            ]);

            if (!$vendorProductId) {
                // حذف المنتج في حال فشل الربط
                wp_delete_post($productId, true);
                throw new ServiceException(__('حدث خطأ أثناء ربط المنتج بالبائع.', 'vmp'));
            }

            // تأكيد المعاملة
            $this->db->query('COMMIT');

            // إطلاق حدث إنشاء المنتج
            try {
                $this->eventManager->trigger(
                    'vmp_product_created',
                    $vendorProductId,
                    $productId,
                    $vendorId
                );
            } catch (Exception $e) {
                $this->logger->error('فشل إطلاق حدث إنشاء المنتج: ' . $e->getMessage());
            }

            return $vendorProductId;

        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * الحصول على الحد الأقصى للمنتجات حسب خطة البائع
     */
    private function getMaxProducts(int $vendorId): int
    {
        try {
            $subRepo = \VMP\Core\Container::getInstance()
                ->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
            $planRepo = \VMP\Core\Container::getInstance()
                ->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);

            $activeSub = $subRepo->findActiveByVendor($vendorId);
            if ($activeSub) {
                $plan = $planRepo->find((int) $activeSub->plan_id);
                if ($plan) {
                    $features = $planRepo->getFeatures((int) $plan->id);
                    if (!empty($features['unlimited_products'])) {
                        return -1; // غير محدود
                    }
                    return (int) $plan->max_products;
                }
            }

            // الخطة المجانية
            $freePlan = $planRepo->findBySlug('free');
            if ($freePlan) {
                return (int) $freePlan->max_products;
            }

            return 10; // القيمة الافتراضية
        } catch (Exception $e) {
            $this->logger->error('فشل الحصول على الحد الأقصى للمنتجات: ' . $e->getMessage());
            return 10;
        }
    }

    /**
     * تحديث منتج
     */
    public function updateProduct(int $vendorProductId, int $vendorId, ProductDTO $dto): void
    {
        $vendorProduct = $this->productRepository->find($vendorProductId);
        if (!$vendorProduct || (int) $vendorProduct->vendor_id !== $vendorId) {
            throw new ServiceException(__('المنتج غير موجود أو لا تملك صلاحية تعديله.', 'vmp'));
        }

        $productId = (int) $vendorProduct->product_id;
        $product = wc_get_product($productId);
        if (!$product) {
            throw new ServiceException(__('بيانات المنتج غير موجودة في WooCommerce.', 'vmp'));
        }

        $this->db->query('START TRANSACTION');

        try {
            if ($dto->title !== '') {
                $product->set_name(sanitize_text_field($dto->title));
            }
            if ($dto->regularPrice > 0) {
                $product->set_regular_price((float) $dto->regularPrice);
            }
            if ($dto->salePrice > 0) {
                $product->set_sale_price((float) $dto->salePrice);
            }
            if ($dto->description !== '') {
                $product->set_description(wp_kses_post($dto->description));
            }
            if ($dto->shortDescription !== '') {
                $product->set_short_description(wp_kses_post($dto->shortDescription));
            }
            if ($dto->sku !== '') {
                $product->set_sku(sanitize_text_field($dto->sku));
            }

            $manageStock = $dto->stockQuantity > 0 || $dto->stockStatus !== 'instock';
            $product->set_manage_stock($manageStock);
            if ($manageStock) {
                $product->set_stock_quantity($dto->stockQuantity);
            }

            if (!empty($dto->categoryIds)) {
                $product->set_category_ids($dto->categoryIds);
            }
            if (!empty($dto->imageId)) {
                $product->set_image_id($dto->imageId);
            }
            if (!empty($dto->galleryImageIds)) {
                $product->set_gallery_image_ids($dto->galleryImageIds);
            }

            $product->save();

            if (isset($dto->isFeatured)) {
                $this->productRepository->update($vendorProductId, [
                    'is_featured' => !empty($dto->isFeatured) ? 1 : 0
                ]);
            }

            $this->db->query('COMMIT');

            try {
                $this->eventManager->trigger(
                    'vmp_product_updated',
                    $vendorProductId,
                    $productId,
                    $vendorId
                );
            } catch (Exception $e) {
                $this->logger->error('فشل إطلاق حدث تحديث المنتج: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * حذف منتج
     */
    public function deleteProduct(int $vendorProductId, int $vendorId): void
    {
        $vendorProduct = $this->productRepository->find($vendorProductId);
        if (!$vendorProduct || (int) $vendorProduct->vendor_id !== $vendorId) {
            throw new ServiceException(__('المنتج غير موجود أو لا تملك صلاحية حذفه.', 'vmp'));
        }

        $productId = (int) $vendorProduct->product_id;
        
        $this->db->query('START TRANSACTION');

        try {
            $this->productRepository->delete($vendorProductId);
            wp_delete_post($productId, true);

            $this->db->query('COMMIT');

            try {
                $this->eventManager->trigger('vmp_product_deleted', $vendorProductId, $productId, $vendorId);
            } catch (Exception $e) {
                $this->logger->error('فشل إطلاق حدث حذف المنتج: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * الموافقة على منتج من قبل الإدارة
     */
    public function approveProduct(int $vendorProductId): void
    {
        $vendorProduct = $this->productRepository->find($vendorProductId);
        if (!$vendorProduct) {
            throw new ServiceException(__('المنتج غير موجود.', 'vmp'));
        }
        if ($vendorProduct->status === 'approved') {
            throw new ServiceException(__('هذا المنتج معتمد مسبقاً.', 'vmp'));
        }

        $this->db->query('START TRANSACTION');

        try {
            $this->productRepository->update($vendorProductId, ['status' => 'approved', 'admin_notes' => '']);
            
            $productId = (int) $vendorProduct->product_id;
            wp_update_post([
                'ID' => $productId,
                'post_status' => 'publish'
            ]);

            $this->db->query('COMMIT');

            try {
                $this->eventManager->trigger('vmp_product_approved', $vendorProductId, $productId);
            } catch (Exception $e) {
                $this->logger->error('فشل إطلاق حدث اعتماد المنتج: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * رفض منتج من قبل الإدارة
     */
    public function rejectProduct(int $vendorProductId, string $reason = ''): void
    {
        $vendorProduct = $this->productRepository->find($vendorProductId);
        if (!$vendorProduct) {
            throw new ServiceException(__('المنتج غير موجود.', 'vmp'));
        }
        if ($vendorProduct->status === 'rejected') {
            throw new ServiceException(__('هذا المنتج مرفوض مسبقاً.', 'vmp'));
        }

        $this->db->query('START TRANSACTION');

        try {
            $this->productRepository->update($vendorProductId, [
                'status' => 'rejected',
                'admin_notes' => sanitize_textarea_field($reason)
            ]);
            
            $productId = (int) $vendorProduct->product_id;
            wp_update_post([
                'ID' => $productId,
                'post_status' => 'draft'
            ]);

            $this->db->query('COMMIT');

            try {
                $this->eventManager->trigger('vmp_product_rejected', $vendorProductId, $productId, $reason);
            } catch (Exception $e) {
                $this->logger->error('فشل إطلاق حدث رفض المنتج: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }
}