<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\SubscriptionRepository;
use VMP\Repositories\SubscriptionPlanRepository;
use VMP\Repositories\VendorRepository;
use VMP\Repositories\ProductRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وحدة الاشتراكات — تدير خطط الاشتراك ودورة حياتها
 */
class Subscription extends AbstractModule
{
    private SubscriptionRepository $repository;
    private SubscriptionPlanRepository $planRepository;
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
        $this->repository = $this->make(SubscriptionRepository::class);
        $this->planRepository = $this->make(SubscriptionPlanRepository::class);
        $this->vendorRepository = $this->make(VendorRepository::class);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        // تم نقل جميع مسارات AJAX إلى ActionDispatcher / RouteRegistry
        // add_action('wp_ajax_vmp_subscribe', [$this, 'ajax_subscribe']);
        // add_action('wp_ajax_vmp_upgrade_plan', [$this, 'ajax_upgrade']);
        // add_action('wp_ajax_vmp_cancel_subscription', [$this, 'ajax_cancel']);
        // add_action('wp_ajax_vmp_get_plans', [$this, 'ajax_get_plans']);
        // add_action('wp_ajax_vmp_admin_create_plan', [$this, 'ajax_admin_create_plan']);
        // add_action('wp_ajax_vmp_admin_update_plan', [$this, 'ajax_admin_update_plan']);
        // add_action('wp_ajax_vmp_admin_delete_plan', [$this, 'ajax_admin_delete_plan']);
        // add_action('wp_ajax_vmp_admin_get_vendor_subscription', [$this, 'ajax_admin_get_vendor_subscription']);
        // add_action('wp_ajax_vmp_request_plan_change', [$this, 'ajax_request_plan_change']);
        // add_action('wp_ajax_vmp_admin_approve_plan_change', [$this, 'ajax_admin_approve_plan_change']);
        // add_action('wp_ajax_vmp_admin_reject_plan_change', [$this, 'ajax_admin_reject_plan_change']);
        // add_action('wp_ajax_vmp_get_pending_plan_changes', [$this, 'ajax_get_pending_plan_changes']);
        // add_action('wp_ajax_vmp_cancel_plan_change', [$this, 'ajax_cancel_plan_change']);

        // حدث اعتماد البائع (يبقى هنا لأنه حدث بالوحدة وليس AJAX)
        $this->container->get('event_manager')->add_listener('vmp_vendor_approved', [$this, 'on_vendor_approved']);
    }

    /**
     * عند اعتماد بائع جديد، يتم منحه الخطة المجانية تلقائياً
     */
    public function on_vendor_approved(int $vendor_id): void
    {
        $free_plan = $this->planRepository->findBySlug('free');
        if (!$free_plan) {
            return;
        }

        if ($this->repository->findActiveByVendor($vendor_id)) {
            return;
        }

        $start_date = current_time('mysql');
        $end_date = date('Y-m-d H:i:s', strtotime('+10 years'));

        $this->repository->create([
            'vendor_id' => $vendor_id,
            'plan_id' => (int) $free_plan->id,
            'status' => 'active',
            'amount' => 0,
            'billing_period' => $free_plan->billing_period,
            'billing_interval' => (int) $free_plan->billing_interval,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * ✅ التحقق مما إذا كان البائع لديه طلب تغيير خطة معلق
     */
    private function hasPendingPlanChange(int $vendor_id): bool
    {
        $pending = $this->repository->getPendingPlanChangeByVendor($vendor_id);
        return $pending !== null;
    }

    /**
     * ✅ التحقق من إمكانية إضافة منتج (مع منع الإضافة أثناء الطلبات المعلقة)
     */
    public function can_add_product(int $vendor_id): bool
    {
        // إذا كان هناك طلب تغيير خطة معلق، نمنع إضافة منتجات جديدة
        if ($this->hasPendingPlanChange($vendor_id)) {
            return false;
        }

        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) {
            return false;
        }

        $active_subscription = $this->repository->findActiveByVendor($vendor_id);
        $productRepository = $this->make(ProductRepository::class);
        $current_count = $productRepository->countByVendor($vendor_id);

        if (!$active_subscription) {
            $free_plan = $this->planRepository->findBySlug('free');
            if (!$free_plan) {
                return $current_count < 10;
            }
            return $this->planRepository->canAddProduct((int) $free_plan->id, $current_count);
        }

        $plan = $this->planRepository->find((int) $active_subscription->plan_id);
        if (!$plan) {
            return false;
        }

        return $this->planRepository->canAddProduct((int) $plan->id, $current_count);
    }

    /**
     * ✅ الحصول على نسبة العمولة
     */
    public function get_commission_rate(int $vendor_id): float
    {
        $active = $this->repository->findActiveByVendor($vendor_id);
        if (!$active) {
            $free = $this->planRepository->findBySlug('free');
            return $free ? (float) $free->commission_rate : (float) get_option('vmp_default_commission', 10);
        }

        $plan = $this->planRepository->find((int) $active->plan_id);
        return $plan ? (float) $plan->commission_rate : (float) get_option('vmp_default_commission', 10);
    }

    /**
     * ✅ التحقق من وجود ميزة معينة للبائع (مع منع الميزات الجديدة أثناء الطلبات المعلقة)
     */
    public function has_feature(int $vendor_id, string $feature): bool
    {
        // إذا كان هناك طلب تغيير خطة معلق، نمنع استخدام الميزات الجديدة
        if ($this->hasPendingPlanChange($vendor_id)) {
            // نتحقق من الخطة الحالية فقط، وليس الخطة المطلوبة
            $active = $this->repository->findActiveByVendor($vendor_id);
            $plan = $active
                ? $this->planRepository->find((int) $active->plan_id)
                : $this->planRepository->findBySlug('free');

            if (!$plan) {
                return false;
            }

            $features = $this->planRepository->getFeatures((int) $plan->id);
            return !empty($features[$feature]);
        }

        // إذا لم يكن هناك طلب معلق، نتحقق كالمعتاد
        $active = $this->repository->findActiveByVendor($vendor_id);
        $plan = $active
            ? $this->planRepository->find((int) $active->plan_id)
            : $this->planRepository->findBySlug('free');

        if (!$plan) {
            return false;
        }

        $features = $this->planRepository->getFeatures((int) $plan->id);
        return !empty($features[$feature]);
    }

    /**
     * ✅ التحقق من انتهاء الاشتراكات وإرجاعها للخطة المجانية
     */
    public function check_expired(): void
    {
        $expired = $this->repository->getExpired();
        foreach ($expired as $subscription) {
            $this->repository->cancel($subscription->id);
            $this->vendorRepository->update((int) $subscription->vendor_id, [
                'subscription_plan' => 'free',
                'subscription_status' => 'active',
                'subscription_expiry' => null,
            ]);

            $this->container->get('logger')->info(
                'انتهى اشتراك البائع وتم إرجاعه للخطة المجانية',
                ['subscription_id' => $subscription->id, 'vendor_id' => $subscription->vendor_id]
            );

            $this->container->get('event_manager')->trigger(
                'vmp_subscription_expired',
                (int) $subscription->id,
                (int) $subscription->vendor_id
            );
        }
    }

    /**
     * ✅ إرسال تذكيرات بانتهاء الاشتراك
     */
    public function send_reminders(): void
    {
        $expiring = $this->repository->getExpiringSoon(7);
        foreach ($expiring as $subscription) {
            $this->container->get('event_manager')->trigger(
                'vmp_subscription_expiring',
                (int) $subscription->id,
                (int) $subscription->vendor_id,
                $subscription->end_date
            );
        }
    }

    /* ──────────────────────────────────────────────────────────── */
    /* أكشنات البائع (الاشتراك، الترقية، الإلغاء، جلب الخطط)      */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * Ajax Subscribe functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_subscribe(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول أولاً', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor || $vendor->status !== 'approved') {
            wp_send_json_error(['message' => __('يجب أن تكون بائعاً معتمداً', 'vmp')]);
        }

        $plan_id = (int) ($_POST['plan_id'] ?? 0);
        $plan = $this->planRepository->find($plan_id);
        if (!$plan || !$plan->is_active) {
            wp_send_json_error(['message' => __('الخطة غير موجودة أو غير متاحة', 'vmp')]);
        }

        $current = $this->repository->findActiveByVendor((int) $vendor->id);
        if ($current) {
            $this->repository->cancel((int) $current->id);
        }

        $start_date = current_time('mysql');
        $end_date = date('Y-m-d H:i:s', strtotime("+{$plan->billing_interval} {$plan->billing_period}"));

        $subscription_id = $this->repository->create([
            'vendor_id' => (int) $vendor->id,
            'plan_id' => $plan_id,
            'status' => 'active',
            'amount' => (float) $plan->price,
            'billing_period' => $plan->billing_period,
            'billing_interval' => (int) $plan->billing_interval,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

        if (!$subscription_id) {
            wp_send_json_error(['message' => __('حدث خطأ أثناء الاشتراك', 'vmp')]);
        }

        $this->vendorRepository->update((int) $vendor->id, [
            'subscription_plan' => $plan->slug,
            'subscription_status' => 'active',
            'subscription_start' => $start_date,
            'subscription_expiry' => $end_date,
        ]);

        $this->container->get('event_manager')->trigger(
            'vmp_subscription_created',
            $subscription_id,
            (int) $vendor->id,
            $plan_id
        );

        wp_send_json_success([
            'message' => __('تم الاشتراك بنجاح', 'vmp'),
            'subscription_id' => $subscription_id,
            'end_date' => $end_date,
            'plan_name' => $plan->name,
        ]);
    }

    /**
     * Ajax Upgrade functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_upgrade(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول أولاً', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor || $vendor->status !== 'approved') {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $new_plan_id = (int) ($_POST['plan_id'] ?? 0);
        $new_plan = $this->planRepository->find($new_plan_id);
        if (!$new_plan || !$new_plan->is_active) {
            wp_send_json_error(['message' => __('الخطة غير موجودة', 'vmp')]);
        }

        $current = $this->repository->findActiveByVendor((int) $vendor->id);
        if ($current && (int) $current->plan_id === $new_plan_id) {
            wp_send_json_error(['message' => __('أنت مشترك بهذه الخطة بالفعل', 'vmp')]);
        }

        if ($current) {
            $this->repository->cancel((int) $current->id);
        }

        $start_date = current_time('mysql');
        $end_date = date('Y-m-d H:i:s', strtotime("+{$new_plan->billing_interval} {$new_plan->billing_period}"));

        $subscription_id = $this->repository->create([
            'vendor_id' => (int) $vendor->id,
            'plan_id' => $new_plan_id,
            'status' => 'active',
            'amount' => (float) $new_plan->price,
            'billing_period' => $new_plan->billing_period,
            'billing_interval' => (int) $new_plan->billing_interval,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

        if (!$subscription_id) {
            wp_send_json_error(['message' => __('حدث خطأ', 'vmp')]);
        }

        $this->vendorRepository->update((int) $vendor->id, [
            'subscription_plan' => $new_plan->slug,
            'subscription_status' => 'active',
            'subscription_start' => $start_date,
            'subscription_expiry' => $end_date,
        ]);

        $this->container->get('event_manager')->trigger(
            'vmp_subscription_upgraded',
            $subscription_id,
            (int) $vendor->id,
            $new_plan_id
        );

        wp_send_json_success([
            'message' => __('تم تغيير الخطة بنجاح', 'vmp'),
            'subscription_id' => $subscription_id,
            'plan_name' => $new_plan->name,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Ajax Cancel functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_cancel(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول أولاً', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $current = $this->repository->findActiveByVendor((int) $vendor->id);
        if (!$current) {
            wp_send_json_error(['message' => __('لا يوجد اشتراك نشط', 'vmp')]);
        }

        $this->repository->cancel((int) $current->id);
        $this->vendorRepository->update((int) $vendor->id, [
            'subscription_plan' => 'free',
            'subscription_status' => 'active',
            'subscription_expiry' => null,
        ]);

        $this->container->get('event_manager')->trigger(
            'vmp_subscription_cancelled',
            (int) $current->id,
            (int) $vendor->id
        );

        wp_send_json_success(['message' => __('تم إلغاء الاشتراك', 'vmp')]);
    }

    /**
     * Ajax Get Plans functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_get_plans(): void
    {
        $plans = $this->planRepository->getAll(true);
        $data = [];
        foreach ($plans as $plan) {
            $data[] = [
                'id' => (int) $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => (float) $plan->price,
                'billing_period' => $plan->billing_period,
                'billing_interval' => (int) $plan->billing_interval,
                'max_products' => (int) $plan->max_products,
                'commission_rate' => (float) $plan->commission_rate,
                'features' => json_decode($plan->features ?? '{}', true),
            ];
        }
        wp_send_json_success(['plans' => $data]);
    }

    /* ──────────────────────────────────────────────────────────── */
    /* أكشنات المشرف (إنشاء، تحديث، حذف الخطط)                    */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * Ajax Admin Create Plan functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_create_plan(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_subscriptions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $features = [];

        if (isset($_POST['features']) && is_array($_POST['features'])) {
            $features = array_map('boolval', $_POST['features']);
        } else {
            $features = [
                'unlimited_products' => !empty($_POST['unlimited_products']),
                'whatsapp_button' => !empty($_POST['whatsapp_button']),
                'custom_domain' => !empty($_POST['custom_domain']),
                'advanced_analytics' => !empty($_POST['advanced_analytics']),
                'coupons' => !empty($_POST['coupons']),
                'trusted_badge' => !empty($_POST['trusted_badge']),
                'priority_support' => !empty($_POST['priority_support']),
                'store_address' => !empty($_POST['store_address']),
                'social_links' => !empty($_POST['social_links']),
                'product_video' => !empty($_POST['product_video']),
            ];
        }

        $plan_id = $this->planRepository->create([
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'price' => (float) ($_POST['price'] ?? 0),
            'billing_period' => sanitize_text_field($_POST['billing_period'] ?? 'month'),
            'billing_interval' => (int) ($_POST['billing_interval'] ?? 1),
            'max_products' => (int) ($_POST['max_products'] ?? 10),
            'commission_rate' => (float) ($_POST['commission_rate'] ?? 10),
            'features' => $features,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ]);

        if ($plan_id) {
            wp_send_json_success(['message' => __('تم إنشاء الخطة بنجاح', 'vmp'), 'plan_id' => $plan_id]);
        }

        wp_send_json_error(['message' => __('حدث خطأ أثناء إنشاء الخطة', 'vmp')]);
    }

    /**
     * Ajax Admin Update Plan functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_update_plan(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_subscriptions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $plan_id = (int) ($_POST['plan_id'] ?? 0);
        $plan = $this->planRepository->find($plan_id);
        if (!$plan) {
            wp_send_json_error(['message' => __('الخطة غير موجودة', 'vmp')]);
        }

        $existing_features = json_decode($plan->features ?? '{}', true) ?: [];

        if (isset($_POST['features']) && is_array($_POST['features'])) {
            $new_features = array_map('boolval', $_POST['features']);
            $features = array_merge($existing_features, $new_features);
        } else {
            $new_features = [
                'unlimited_products' => !empty($_POST['unlimited_products']),
                'whatsapp_button' => !empty($_POST['whatsapp_button']),
                'custom_domain' => !empty($_POST['custom_domain']),
                'advanced_analytics' => !empty($_POST['advanced_analytics']),
                'coupons' => !empty($_POST['coupons']),
                'trusted_badge' => !empty($_POST['trusted_badge']),
                'priority_support' => !empty($_POST['priority_support']),
                'store_address' => !empty($_POST['store_address']),
                'social_links' => !empty($_POST['social_links']),
                'product_video' => !empty($_POST['product_video']),
            ];
            $features = array_merge($existing_features, $new_features);
        }

        $update = [
            'name' => sanitize_text_field($_POST['name'] ?? $plan->name),
            'description' => sanitize_textarea_field($_POST['description'] ?? $plan->description),
            'price' => (float) ($_POST['price'] ?? $plan->price),
            'billing_period' => sanitize_text_field($_POST['billing_period'] ?? $plan->billing_period),
            'billing_interval' => (int) ($_POST['billing_interval'] ?? $plan->billing_interval),
            'max_products' => (int) ($_POST['max_products'] ?? $plan->max_products),
            'commission_rate' => (float) ($_POST['commission_rate'] ?? $plan->commission_rate),
            'features' => $features,
            'sort_order' => (int) ($_POST['sort_order'] ?? $plan->sort_order),
            'is_active' => (int) ($_POST['is_active'] ?? $plan->is_active),
        ];

        if ($this->planRepository->update($plan_id, $update)) {
            wp_send_json_success(['message' => __('تم تحديث الخطة بنجاح', 'vmp')]);
        }

        wp_send_json_error(['message' => __('لم يتم تحديث أي بيانات', 'vmp')]);
    }

    /**
     * Ajax Admin Delete Plan functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_delete_plan(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_subscriptions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $plan_id = (int) ($_POST['plan_id'] ?? 0);
        if ($this->planRepository->delete($plan_id)) {
            wp_send_json_success(['message' => __('تم حذف الخطة', 'vmp')]);
        }

        wp_send_json_error(['message' => __('حدث خطأ', 'vmp')]);
    }

    /**
     * Ajax Admin Get Vendor Subscription functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_get_vendor_subscription(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');
        if (!current_user_can('vmp_manage_subscriptions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $vendor_id = (int) ($_POST['vendor_id'] ?? 0);
        $active = $this->repository->findActiveByVendor($vendor_id);
        $plan = $active ? $this->planRepository->find((int) $active->plan_id) : null;

        wp_send_json_success([
            'subscription' => $active,
            'plan' => $plan,
        ]);
    }

    /* ──────────────────────────────────────────────────────────── */
    /* طلبات تغيير الخطة                                           */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * Ajax Request Plan Change functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_request_plan_change(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول أولاً', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor || $vendor->status !== 'approved') {
            wp_send_json_error(['message' => __('يجب أن تكون بائعاً معتمداً', 'vmp')]);
        }

        $new_plan_id = (int) ($_POST['plan_id'] ?? 0);
        $new_plan = $this->planRepository->find($new_plan_id);
        if (!$new_plan || !$new_plan->is_active) {
            wp_send_json_error(['message' => __('الخطة غير موجودة أو غير متاحة', 'vmp')]);
        }

        $current = $this->repository->findActiveByVendor((int) $vendor->id);
        if ($current && (int) $current->plan_id === $new_plan_id) {
            wp_send_json_error(['message' => __('أنت مشترك بهذه الخطة بالفعل', 'vmp')]);
        }

        $pending = $this->repository->getPendingPlanChangeByVendor((int) $vendor->id);
        if ($pending) {
            wp_send_json_error(['message' => __('لديك طلب تغيير خطة معلق بالفعل، يرجى انتظار المراجعة.', 'vmp')]);
        }

        $request_id = $this->repository->requestPlanChange((int) $vendor->id, $new_plan_id);

        if ($request_id) {
            // إرسال إشعار للمشرف
            $this->sendNotificationToAdmin($vendor, $new_plan, $request_id);

            // إطلاق حدث طلب تغيير الخطة
            $this->container->get('event_manager')->trigger(
                'vmp_plan_change_requested',
                $request_id,
                (int) $vendor->id,
                $new_plan_id
            );

            // تسجيل في السجلات
            if (function_exists('vmp_log_info')) {
                vmp_log_info(
                    sprintf(
                        __('البائع %s طلب تغيير الخطة إلى %s', 'vmp'),
                        $vendor->store_name,
                        $new_plan->name
                    ),
                    [
                        'vendor_id' => $vendor->id,
                        'plan_id' => $new_plan_id,
                        'request_id' => $request_id,
                    ],
                    'Subscription'
                );
            }

            wp_send_json_success([
                'message' => __('تم إرسال طلب تغيير الخطة بنجاح، سيتم مراجعته من قبل المشرف وإشعارك عند الرد.', 'vmp'),
                'request_id' => $request_id,
            ]);
        }

        wp_send_json_error(['message' => __('حدث خطأ أثناء إرسال الطلب، يرجى المحاولة مرة أخرى.', 'vmp')]);
    }

    /**
     * Ajax Admin Approve Plan Change functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_approve_plan_change(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');

        if (!current_user_can('vmp_manage_subscriptions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $request_id = (int) ($_POST['request_id'] ?? 0);
        if ($request_id <= 0) {
            wp_send_json_error(['message' => __('معرف الطلب غير صالح', 'vmp')]);
        }

        if ($this->repository->approvePlanChange($request_id)) {
            $subscription = $this->repository->find($request_id);
            if ($subscription) {
                $plan = $this->planRepository->find((int) $subscription->plan_id);
                $vendor = $this->vendorRepository->find((int) $subscription->vendor_id);

                if ($plan && $vendor) {
                    // تحديث بيانات البائع
                    $this->vendorRepository->update((int) $subscription->vendor_id, [
                        'subscription_plan' => $plan->slug,
                        'subscription_status' => 'active',
                        'subscription_start' => current_time('mysql'),
                        'subscription_expiry' => $subscription->end_date,
                    ]);

                    // إرسال إشعار للبائع
                    $this->sendNotificationToVendor(
                        $vendor,
                        $plan,
                        'approved',
                        $subscription
                    );

                    // تسجيل نجاح
                    if (function_exists('vmp_log_success')) {
                        vmp_log_success(
                            sprintf(
                                __('تمت الموافقة على تغيير خطة البائع %s إلى %s', 'vmp'),
                                $vendor->store_name,
                                $plan->name
                            ),
                            [
                                'vendor_id' => $vendor->id,
                                'plan_id' => $plan->id,
                                'request_id' => $request_id,
                            ],
                            'Subscription'
                        );
                    }

                    // إطلاق حدث الموافقة
                    $this->container->get('event_manager')->trigger(
                        'vmp_plan_change_approved',
                        $request_id,
                        (int) $subscription->vendor_id,
                        (int) $subscription->plan_id
                    );
                }
            }
            wp_send_json_success(['message' => __('تمت الموافقة على تغيير الخطة بنجاح وإرسال إشعار للبائع.', 'vmp')]);
        }

        wp_send_json_error(['message' => __('حدث خطأ أثناء الموافقة.', 'vmp')]);
    }

    /**
     * Ajax Admin Reject Plan Change functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_reject_plan_change(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');

        if (!current_user_can('vmp_manage_subscriptions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $request_id = (int) ($_POST['request_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if ($request_id <= 0) {
            wp_send_json_error(['message' => __('معرف الطلب غير صالح', 'vmp')]);
        }

        if ($this->repository->rejectPlanChange($request_id, $reason)) {
            $subscription = $this->repository->find($request_id);
            if ($subscription) {
                $vendor = $this->vendorRepository->find((int) $subscription->vendor_id);
                $plan = $this->planRepository->find((int) $subscription->plan_id);

                if ($vendor) {
                    // إرسال إشعار للبائع
                    $this->sendNotificationToVendor(
                        $vendor,
                        $plan,
                        'rejected',
                        $subscription,
                        $reason
                    );

                    // تسجيل تحذير
                    if (function_exists('vmp_log_warning')) {
                        vmp_log_warning(
                            sprintf(
                                __('تم رفض تغيير خطة البائع %s', 'vmp'),
                                $vendor->store_name
                            ),
                            [
                                'vendor_id' => $vendor->id,
                                'plan_id' => $plan ? $plan->id : 0,
                                'request_id' => $request_id,
                                'reason' => $reason,
                            ],
                            'Subscription'
                        );
                    }

                    // إطلاق حدث الرفض
                    $this->container->get('event_manager')->trigger(
                        'vmp_plan_change_rejected',
                        $request_id,
                        (int) $subscription->vendor_id,
                        $reason
                    );
                }
            }
            wp_send_json_success(['message' => __('تم رفض تغيير الخطة وإرسال إشعار للبائع.', 'vmp')]);
        }

        wp_send_json_error(['message' => __('حدث خطأ أثناء الرفض.', 'vmp')]);
    }

    /**
     * Ajax Get Pending Plan Changes functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_get_pending_plan_changes(): void
    {
        check_ajax_referer('vmp_admin_nonce', 'nonce');

        if (!current_user_can('vmp_manage_subscriptions')) {
            wp_send_json_error(['message' => __('غير مصرح لك', 'vmp')]);
        }

        $pending = $this->repository->getPendingPlanChanges();
        wp_send_json_success(['requests' => $pending]);
    }

    /**
     * Ajax Cancel Plan Change functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_cancel_plan_change(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول أولاً', 'vmp')]);
        }

        $user_id = get_current_user_id();
        $vendor = $this->vendorRepository->findByUserId($user_id);
        if (!$vendor) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $pending = $this->repository->getPendingPlanChangeByVendor((int) $vendor->id);
        if (!$pending) {
            wp_send_json_error(['message' => __('لا يوجد طلب تغيير خطة معلق.', 'vmp')]);
        }

        if ($this->repository->forceDelete((int) $pending->id)) {
            wp_send_json_success(['message' => __('تم إلغاء طلب تغيير الخطة.', 'vmp')]);
        }

        wp_send_json_error(['message' => __('حدث خطأ أثناء إلغاء الطلب.', 'vmp')]);
    }

    /* ──────────────────────────────────────────────────────────── */
    /* دوال مساعدة للإشعارات                                       */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * إرسال إشعار للبائع عند الموافقة أو الرفض
     */
    private function sendNotificationToVendor(object $vendor, ?object $plan, string $status, object $subscription, string $reason = ''): void
    {
        $user = get_userdata($vendor->user_id);
        if (!$user) {
            return;
        }

        $plan_name = $plan ? $plan->name : __('غير معروف', 'vmp');

        if ($status === 'approved') {
            $subject = __('✅ تم الموافقة على تغيير خطتك', 'vmp');
            $message = sprintf(
                __(
                    "مرحباً %s،\n\n"
                    . "تمت الموافقة على طلب تغيير خطتك إلى: %s\n"
                    . "تاريخ البدء: %s\n"
                    . "تاريخ الانتهاء: %s\n\n"
                    . "يمكنك الآن الاستفادة من ميزات خطتك الجديدة.\n\n"
                    . "شكراً لانضمامك إلينا.",
                    'vmp'
                ),
                $vendor->store_name,
                $plan_name,
                date_i18n('Y-m-d', strtotime($subscription->start_date)),
                date_i18n('Y-m-d', strtotime($subscription->end_date))
            );
        } else {
            $subject = __('❌ تم رفض طلب تغيير خطتك', 'vmp');
            $message = sprintf(
                __(
                    "مرحباً %s،\n\n"
                    . "نأسف لإبلاغك بأن طلب تغيير خطتك إلى %s قد تم رفضه.\n",
                    'vmp'
                ),
                $vendor->store_name,
                $plan_name
            );

            if (!empty($reason)) {
                $message .= sprintf(
                    __("سبب الرفض: %s\n", 'vmp'),
                    $reason
                );
            }

            $message .= "\n" . __('يمكنك التقدم بطلب آخر في أي وقت.', 'vmp');
        }

        wp_mail(
            $user->user_email,
            $subject,
            nl2br($message),
            ['Content-Type: text/html; charset=UTF-8']
        );

        // إضافة إشعار في لوحة تحكم البائع
        $this->addVendorDashboardNotice(
            (int) $vendor->id,
            $subject,
            $message,
            $status === 'approved' ? 'success' : 'error'
        );
    }

    /**
     * إرسال إشعار للمشرف عند طلب تغيير الخطة
     */
    private function sendNotificationToAdmin(object $vendor, object $plan, int $request_id): void
    {
        $admin_email = get_option('admin_email');
        $subject = sprintf(
            __('📋 طلب تغيير خطة من %s', 'vmp'),
            $vendor->store_name
        );

        $message = sprintf(
            __(
                "مرحباً،\n\n"
                . "قام البائع %s بطلب تغيير خطته إلى: %s\n"
                . "للموافقة أو الرفض، يرجى زيارة لوحة التحكم:\n"
                . "%s\n\n"
                . "معرف الطلب: %d",
                'vmp'
            ),
            $vendor->store_name,
            $plan->name,
            admin_url('admin.php?page=vmp-subscriptions'),
            $request_id
        );

        wp_mail(
            $admin_email,
            $subject,
            nl2br($message),
            ['Content-Type: text/html; charset=UTF-8']
        );

        // إشعار داخل لوحة تحكم المشرف
        $this->addAdminNotice(
            sprintf(
                __('طلب تغيير خطة من %s إلى %s', 'vmp'),
                $vendor->store_name,
                $plan->name
            ),
            'pending_plan_change',
            $request_id
        );
    }

    /**
     * إضافة إشعار في لوحة تحكم البائع
     */
    private function addVendorDashboardNotice(int $vendor_id, string $title, string $message, string $type = 'success'): void
    {
        $notices = get_user_meta($vendor_id, 'vmp_dashboard_notices', true);
        if (!is_array($notices)) {
            $notices = [];
        }

        $notices[] = [
            'id' => uniqid(),
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'created_at' => current_time('mysql'),
            'read' => false,
        ];

        // الاحتفاظ بأحدث 50 إشعار فقط
        if (count($notices) > 50) {
            $notices = array_slice($notices, -50);
        }

        update_user_meta($vendor_id, 'vmp_dashboard_notices', $notices);
    }

    /**
     * إضافة إشعار في لوحة تحكم المشرف
     */
    private function addAdminNotice(string $message, string $type, int $request_id): void
    {
        $notices = get_option('vmp_admin_notices', []);
        if (!is_array($notices)) {
            $notices = [];
        }

        $notices[] = [
            'id' => $request_id,
            'message' => $message,
            'type' => $type,
            'created_at' => current_time('mysql'),
            'read' => false,
        ];

        update_option('vmp_admin_notices', $notices);
    }
}