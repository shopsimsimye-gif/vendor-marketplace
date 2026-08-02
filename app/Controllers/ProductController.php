<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\ProductService;
use VMP\Http\Requests\CreateProductRequest;
use VMP\Http\Requests\UpdateProductRequest;
use VMP\Http\Requests\DeleteProductRequest;
use VMP\Http\Requests\AdminProductActionRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ApiResponse;
use VMP\Exceptions\ServiceException;
use VMP\Core\Logger;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Container;

/**
 * Class ProductController
 * مسؤول عن إدارة منتجات البائعين (إضافة، تحديث، حذف، موافقة، رفض)
 */
class ProductController
{
    public function __construct(
        private ProductService $productService,
        private Logger $logger
    ) {}

    /**
     * ✅ إضافة منتج جديد (الإصلاح الكامل)
     * - التحقق من الصلاحية عبر CreateProductRequest
     * - التحقق من وجود vendor_id
     * - معالجة الأخطاء التفصيلية
     * - تسجيل الأخطاء في السجلات
     */
    public function addProduct(CreateProductRequest $request): ApiResponse
    {
        try {
            // 1. تحويل الطلب إلى DTO (يتضمن التحقق من الصلاحية والتحقق من البيانات)
            $dto = $request->toDTO();

            // 2. التحقق من وجود vendor_id
            if ($dto->vendorId <= 0) {
                // محاولة إصلاح المشكلة تلقائياً: جلب البائع من المستخدم الحالي
                $userId = get_current_user_id();
                if ($userId) {
                    try {
                        $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
                        $vendor = $vendorRepo->findByUserId($userId);
                        if ($vendor) {
                            // إعادة إنشاء DTO مع vendor_id الصحيح
                            $data = $request->validated();
                            $data['vendor_id'] = (int) $vendor->id;
                            $dto = $dto::fromArray($data);
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('فشل جلب البائع من المستودع: ' . $e->getMessage());
                    }
                }

                // إذا ما زال vendor_id = 0، نرمي خطأ
                if ($dto->vendorId <= 0) {
                    throw new ServiceException(__('لم يتم تحديد البائع. يرجى تسجيل الدخول كبائع معتمد.', 'vmp'));
                }
            }

            // 3. التحقق من وجود اسم المنتج
            if (empty($dto->title)) {
                throw new ServiceException(__('اسم المنتج مطلوب.', 'vmp'));
            }

            // 4. استدعاء الخدمة لإضافة المنتج
            $vendorProductId = $this->productService->addProduct($dto->vendorId, $dto);

            // 5. إرجاع استجابة نجاح
            return new SuccessResponse(
                data: [
                    'vendor_product_id' => $vendorProductId,
                    'product_id' => $dto->productId ?? 0,
                ],
                message: __('تم إضافة المنتج بنجاح.', 'vmp'),
                statusCode: 201
            );

        } catch (ServiceException $e) {
            // أخطاء متوقعة من طبقة الخدمة
            $this->logger->warning('فشل إضافة المنتج (ServiceException): ' . $e->getMessage(), [
                'vendor_id' => $dto->vendorId ?? 0,
                'title' => $dto->title ?? '',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // يتم التقاطها بواسطة ExceptionHandler

        } catch (\InvalidArgumentException $e) {
            // أخطاء تتعلق بالـ DTO أو البيانات غير الصالحة
            $this->logger->error('بيانات غير صالحة في إضافة المنتج: ' . $e->getMessage(), [
                'data' => $request->all()
            ]);
            throw new ServiceException(__('بيانات المنتج غير صالحة. يرجى التحقق من الحقول.', 'vmp'), 422, $e);

        } catch (\Exception $e) {
            // أخطاء غير متوقعة
            $this->logger->critical('خطأ غير متوقع في إضافة المنتج: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ServiceException(__('حدث خطأ غير متوقع أثناء إضافة المنتج. يرجى المحاولة مرة أخرى.', 'vmp'), 500, $e);
        }
    }

    /**
     * ✅ تحديث منتج
     */
    public function updateProduct(UpdateProductRequest $request): ApiResponse
    {
        try {
            $dto = $request->toDTO();

            if ($dto->vendorId <= 0) {
                throw new ServiceException(__('لم يتم تحديد البائع.', 'vmp'));
            }

            $this->productService->updateProduct($dto->productId, $dto->vendorId, $dto);

            return new SuccessResponse(
                message: __('تم تحديث المنتج بنجاح.', 'vmp')
            );

        } catch (ServiceException $e) {
            $this->logger->warning('فشل تحديث المنتج: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('خطأ غير متوقع في تحديث المنتج: ' . $e->getMessage());
            throw new ServiceException(__('حدث خطأ غير متوقع أثناء تحديث المنتج.', 'vmp'));
        }
    }

    /**
     * ✅ حذف منتج
     */
    public function deleteProduct(DeleteProductRequest $request): ApiResponse
    {
        try {
            $data = $request->validated();

            if (empty($data['vendor_id'])) {
                // محاولة جلب vendor_id من المستخدم الحالي
                $userId = get_current_user_id();
                try {
                    $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
                    $vendor = $vendorRepo->findByUserId($userId);
                    if ($vendor) {
                        $data['vendor_id'] = (int) $vendor->id;
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('فشل جلب البائع للحذف: ' . $e->getMessage());
                }

                if (empty($data['vendor_id'])) {
                    throw new ServiceException(__('لم يتم تحديد البائع.', 'vmp'));
                }
            }

            $this->productService->deleteProduct($data['product_id'], $data['vendor_id']);

            return new SuccessResponse(
                message: __('تم حذف المنتج بنجاح.', 'vmp')
            );

        } catch (ServiceException $e) {
            $this->logger->warning('فشل حذف المنتج: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('خطأ غير متوقع في حذف المنتج: ' . $e->getMessage());
            throw new ServiceException(__('حدث خطأ غير متوقع أثناء حذف المنتج.', 'vmp'));
        }
    }

    /**
     * ✅ الموافقة على منتج من قبل المشرف
     */
    public function adminApprove(AdminProductActionRequest $request): ApiResponse
    {
        try {
            $data = $request->validated();

            if (empty($data['vendor_product_id'])) {
                throw new ServiceException(__('معرف المنتج غير صالح.', 'vmp'));
            }

            $this->productService->approveProduct($data['vendor_product_id']);

            return new SuccessResponse(
                message: __('تم الموافقة على المنتج بنجاح.', 'vmp')
            );

        } catch (ServiceException $e) {
            $this->logger->warning('فشل الموافقة على المنتج: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('خطأ غير متوقع في الموافقة على المنتج: ' . $e->getMessage());
            throw new ServiceException(__('حدث خطأ غير متوقع أثناء الموافقة.', 'vmp'));
        }
    }

    /**
     * ✅ رفض منتج من قبل المشرف
     */
    public function adminReject(AdminProductActionRequest $request): ApiResponse
    {
        try {
            $data = $request->validated();

            if (empty($data['vendor_product_id'])) {
                throw new ServiceException(__('معرف المنتج غير صالح.', 'vmp'));
            }

            $this->productService->rejectProduct(
                $data['vendor_product_id'],
                $data['reason'] ?? ''
            );

            return new SuccessResponse(
                message: __('تم رفض المنتج بنجاح.', 'vmp')
            );

        } catch (ServiceException $e) {
            $this->logger->warning('فشل رفض المنتج: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('خطأ غير متوقع في رفض المنتج: ' . $e->getMessage());
            throw new ServiceException(__('حدث خطأ غير متوقع أثناء الرفض.', 'vmp'));
        }
    }
}