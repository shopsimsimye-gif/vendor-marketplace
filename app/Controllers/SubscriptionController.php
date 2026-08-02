<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\SubscriptionService;
use VMP\Contracts\SubscriptionPlanRepositoryInterface;
use VMP\Http\Requests\SubscribeRequest;
use VMP\Http\Requests\CancelSubscriptionRequest;
use VMP\Http\Requests\AdminCreatePlanRequest;
use VMP\Http\Requests\AdminUpdatePlanRequest;
use VMP\Http\Requests\AdminDeletePlanRequest;
use VMP\Http\Requests\RequestPlanChangeRequest;
use VMP\Http\Requests\AdminApprovePlanChangeRequest;
use VMP\Http\Requests\AdminRejectPlanChangeRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ApiResponse;
use VMP\Exceptions\ServiceException;

/**
 * Class SubscriptionController
 *
 * Description of administrative platform component SubscriptionController.
 *
 * @package vendor-marketplace
 */
class SubscriptionController extends BaseController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private SubscriptionPlanRepositoryInterface $planRepository
    ) {}

    /**
     * جلب الخطط المتاحة (عام)
     */
    public function getPlans(): ApiResponse
    {
        $plans = $this->planRepository->getAll(true);
        $data = array_map(fn($plan) => [
            'id'               => (int) $plan->id,
            'name'             => $plan->name,
            'slug'             => $plan->slug,
            'description'      => $plan->description,
            'price'            => (float) $plan->price,
            'billing_period'   => $plan->billing_period,
            'billing_interval' => (int) $plan->billing_interval,
            'max_products'     => (int) $plan->max_products,
            'commission_rate'  => (float) $plan->commission_rate,
            'features'         => json_decode($plan->features ?? '{}', true),
        ], $plans);

        return new SuccessResponse(data: ['plans' => $data]);
    }

    /**
     * اشتراك في خطة (للبائع)
     */
    public function subscribe(SubscribeRequest $request): ApiResponse
    {
        $data = $request->validated();
        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new ServiceException(__('يجب أن تكون بائعاً معتمداً', 'vmp'));
        }

        $subscriptionId = $this->subscriptionService->subscribe($vendorId, $data['plan_id']);
        $plan = $this->planRepository->find($data['plan_id']);

        return new SuccessResponse(
            data: ['subscription_id' => $subscriptionId, 'plan_name' => $plan?->name],
            message: __('تم الاشتراك بنجاح', 'vmp')
        );
    }

    /**
     * إلغاء الاشتراك (للبائع)
     */
    public function cancelSubscription(CancelSubscriptionRequest $request): ApiResponse
    {
        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $this->subscriptionService->cancelSubscription($vendorId);

        return new SuccessResponse(message: __('تم إلغاء الاشتراك', 'vmp'));
    }

    /**
     * طلب تغيير خطة (للبائع)
     */
    public function requestPlanChange(RequestPlanChangeRequest $request): ApiResponse
    {
        $data = $request->validated();
        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $requestId = $this->subscriptionService->requestPlanChange($vendorId, $data['plan_id']);

        return new SuccessResponse(
            data: ['request_id' => $requestId],
            message: __('تم إرسال طلب تغيير الخطة بنجاح، سيتم مراجعته من قبل المشرف.', 'vmp')
        );
    }

    /**
     * إلغاء طلب تغيير الخطة (للبائع)
     */
    public function cancelPlanChange(CancelSubscriptionRequest $request): ApiResponse
    {
        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $pending = $this->subscriptionService->getPendingPlanChange($vendorId);
        if (!$pending) {
            throw new ServiceException(__('لا يوجد طلب تغيير خطة معلق.', 'vmp'));
        }

        $this->subscriptionService->cancelPendingPlanChange((int) $pending->id);

        return new SuccessResponse(message: __('تم إلغاء طلب تغيير الخطة.', 'vmp'));
    }

    /**
     * إنشاء خطة جديدة (للمشرف)
     */
    public function adminCreatePlan(AdminCreatePlanRequest $request): ApiResponse
    {
        $data = $request->validated();

        $features = $this->extractFeatures($data);
        $planId = $this->planRepository->create([
            'name'             => sanitize_text_field($data['name'] ?? ''),
            'description'      => sanitize_textarea_field($data['description'] ?? ''),
            'price'            => (float) ($data['price'] ?? 0),
            'billing_period'   => sanitize_text_field($data['billing_period'] ?? 'month'),
            'billing_interval' => (int) ($data['billing_interval'] ?? 1),
            'max_products'     => (int) ($data['max_products'] ?? 10),
            'commission_rate'  => (float) ($data['commission_rate'] ?? 10),
            'features'         => $features,
            'sort_order'       => (int) ($data['sort_order'] ?? 0),
        ]);

        if (!$planId) {
            throw new ServiceException(__('حدث خطأ أثناء إنشاء الخطة', 'vmp'));
        }

        return new SuccessResponse(
            data: ['plan_id' => $planId],
            message: __('تم إنشاء الخطة بنجاح', 'vmp')
        );
    }

    /**
     * تحديث خطة (للمشرف)
     */
    public function adminUpdatePlan(AdminUpdatePlanRequest $request): ApiResponse
    {
        $data = $request->validated();
        $planId = (int) $data['plan_id'];

        $plan = $this->planRepository->find($planId);
        if (!$plan) {
            throw new ServiceException(__('الخطة غير موجودة', 'vmp'));
        }

        $existingFeatures = json_decode($plan->features ?? '{}', true) ?: [];
        $newFeatures = $this->extractFeatures($data);
        $features = array_merge($existingFeatures, $newFeatures);

        $this->planRepository->update($planId, [
            'name'             => sanitize_text_field($data['name'] ?? $plan->name),
            'description'      => sanitize_textarea_field($data['description'] ?? $plan->description),
            'price'            => (float) ($data['price'] ?? $plan->price),
            'billing_period'   => sanitize_text_field($data['billing_period'] ?? $plan->billing_period),
            'billing_interval' => (int) ($data['billing_interval'] ?? $plan->billing_interval),
            'max_products'     => (int) ($data['max_products'] ?? $plan->max_products),
            'commission_rate'  => (float) ($data['commission_rate'] ?? $plan->commission_rate),
            'features'         => $features,
            'sort_order'       => (int) ($data['sort_order'] ?? $plan->sort_order),
            'is_active'        => (int) ($data['is_active'] ?? $plan->is_active),
        ]);

        return new SuccessResponse(message: __('تم تحديث الخطة بنجاح', 'vmp'));
    }

    /**
     * حذف خطة (للمشرف)
     */
    public function adminDeletePlan(AdminDeletePlanRequest $request): ApiResponse
    {
        $data = $request->validated();
        $planId = (int) $data['plan_id'];

        if (!$this->planRepository->delete($planId)) {
            throw new ServiceException(__('حدث خطأ أثناء حذف الخطة', 'vmp'));
        }

        return new SuccessResponse(message: __('تم حذف الخطة', 'vmp'));
    }

    /**
     * جلب اشتراك بائع محدد (للمشرف)
     */
    public function adminGetVendorSubscription(AdminCreatePlanRequest $request): ApiResponse
    {
        $data = $request->validated();
        $result = $this->subscriptionService->getVendorSubscriptionDetails((int) ($data['vendor_id'] ?? 0));

        return new SuccessResponse(data: $result);
    }

    /**
     * جلب طلبات تغيير الخطة المعلقة (للمشرف)
     */
    public function adminGetPendingPlanChanges(): ApiResponse
    {
        $pending = $this->subscriptionService->getAllPendingPlanChanges();

        return new SuccessResponse(data: ['requests' => $pending]);
    }

    /**
     * الموافقة على تغيير خطة (للمشرف)
     */
    public function adminApprovePlanChange(AdminApprovePlanChangeRequest $request): ApiResponse
    {
        $data = $request->validated();
        $this->subscriptionService->approvePlanChange((int) $data['request_id']);

        return new SuccessResponse(message: __('تمت الموافقة على تغيير الخطة بنجاح.', 'vmp'));
    }

    /**
     * رفض تغيير خطة (للمشرف)
     */
    public function adminRejectPlanChange(AdminRejectPlanChangeRequest $request): ApiResponse
    {
        $data = $request->validated();
        $this->subscriptionService->rejectPlanChange(
            (int) $data['request_id'],
            $data['reason'] ?? ''
        );

        return new SuccessResponse(message: __('تم رفض تغيير الخطة.', 'vmp'));
    }

    /**
     * استخراج الميزات من بيانات الطلب
     */
    private function extractFeatures(array $data): array
    {
        if (isset($data['features']) && is_array($data['features'])) {
            return array_map('boolval', $data['features']);
        }

        $featureKeys = [
            'unlimited_products', 'whatsapp_button', 'custom_domain',
            'advanced_analytics', 'coupons', 'trusted_badge',
            'priority_support', 'store_address', 'social_links', 'product_video',
        ];

        $features = [];
        foreach ($featureKeys as $key) {
            if (isset($data[$key])) {
                $features[$key] = (bool) $data[$key];
            }
        }
        return $features;
    }
}
