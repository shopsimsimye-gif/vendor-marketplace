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

    /**
     * Ajax Upgrade functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Cancel functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Get Plans functionality helper.
     *
     * @return void Output payload.
     */

    /* ──────────────────────────────────────────────────────────── */
    /* أكشنات المشرف (إنشاء، تحديث، حذف الخطط)                    */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * Ajax Admin Create Plan functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Admin Update Plan functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Admin Delete Plan functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Admin Get Vendor Subscription functionality helper.
     *
     * @return void Output payload.
     */

    /* ──────────────────────────────────────────────────────────── */
    /* طلبات تغيير الخطة                                           */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * Ajax Request Plan Change functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Admin Approve Plan Change functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Admin Reject Plan Change functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Get Pending Plan Changes functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Cancel Plan Change functionality helper.
     *
     * @return void Output payload.
     */

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