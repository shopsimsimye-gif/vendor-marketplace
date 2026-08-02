<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\VendorRequestRepositoryInterface;
use VMP\Core\Queue\QueueManager;
use VMP\Jobs\SendEmailJob;

/**
 * Class NotificationService
 *
 * يتولى تجهيز الإشعارات وإرسالها بشكل غير متزامن عبر طابور المهام في الخلفية
 */
class NotificationService
{
    public function __construct(
        private VendorRepositoryInterface $vendorRepository,
        private VendorRequestRepositoryInterface $requestRepository,
        private ?QueueManager             $queueManager = null
    ) {}

    /**
     * إرسال إيميل (إما متزامن أو غير متزامن عبر الطابور)
     */
    private function sendEmail(string $to, string $subject, string $body, array $headers = []): void
    {
        if ($this->queueManager !== null) {
            $this->queueManager->push(SendEmailJob::class, [
                'to'      => $to,
                'subject' => $subject,
                'body'    => $body,
                'headers' => $headers,
            ]);
        } else {
            // Fallback في حالة عدم حقن QueueManager (مثل بيئات الاختبار أو الموديولات القديمة)
            wp_mail($to, $subject, $body, $headers);
        }
    }

    /**
     * SendVendorRequestSubmittedNotification functionality helper.
     * يُرسل عند تقديم طلب انضمام جديد
     *
     * @param int $requestId وصف
     * @return void
     */
    public function sendVendorRequestSubmittedNotification(int $requestId): void
    {
        $request = $this->requestRepository->find($requestId);
        if (!$request) return;

        $user = get_userdata($request->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        
        // إشعار للمستخدم
        if (!empty($settings['email_vendor_request_submitted'])) {
            $message = apply_filters('vmp_vendor_request_submitted_message_user', 
                sprintf(__('مرحباً %s، تم استلام طلب انضمامك كبائع للمتجر "%s" وهو قيد المراجعة.', 'vmp'), 
                    $user->display_name, $request->store_name),
                $requestId, $request
            );
            $this->sendEmail(
                $user->user_email,
                __('تم استلام طلب انضمامك', 'vmp'),
                $message
            );
        }

        // إشعار للمشرف
        if (!empty($settings['email_admin_new_vendor_request'])) {
            $adminEmail = $settings['admin_email'] ?? get_option('admin_email');
            $message = apply_filters('vmp_vendor_request_submitted_message_admin',
                sprintf(__('تم استلام طلب انضمام بائع جديد: %s (المستخدم: %s)', 'vmp'), 
                    $request->store_name, $user->user_email),
                $requestId, $request
            );
            $this->sendEmail(
                $adminEmail,
                __('طلب انضمام بائع جديد', 'vmp'),
                $message
            );
        }
    }

    /**
     * SendVendorRequestApprovedNotification functionality helper.
     * يُرسل عند الموافقة على طلب الانضمام
     *
     * @param int $requestId وصف
     * @return void
     */
    public function sendVendorRequestApprovedNotification(int $requestId): void
    {
        $request = $this->requestRepository->find($requestId);
        if (!$request) return;

        $user = get_userdata($request->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        
        if (!empty($settings['email_vendor_request_approved'])) {
            $message = apply_filters('vmp_vendor_request_approved_message',
                sprintf(__('تهانينا %s! تم قبول طلب انضمامك كبائع للمتجر "%s". يمكنك الآن البدء في إضافة منتجاتك.', 'vmp'), 
                    $user->display_name, $request->store_name),
                $requestId, $request
            );
            $this->sendEmail(
                $user->user_email,
                __('تم قبول طلبك كبائع!', 'vmp'),
                $message
            );
        }
    }

    /**
     * SendVendorRequestRejectedNotification functionality helper.
     * يُرسل عند رفض طلب الانضمام
     *
     * @param int $requestId وصف
     * @param string $reason سبب الرفض
     * @return void
     */
    public function sendVendorRequestRejectedNotification(int $requestId, string $reason): void
    {
        $request = $this->requestRepository->find($requestId);
        if (!$request) return;

        $user = get_userdata($request->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        
        if (!empty($settings['email_vendor_request_rejected'])) {
            $message = apply_filters('vmp_vendor_request_rejected_message',
                sprintf(__('نعتذر %s، تم رفض طلب انضمامك كبائع للمتجر "%s". السبب: %s', 'vmp'), 
                    $user->display_name, $request->store_name, $reason),
                $requestId, $request
            );
            $this->sendEmail(
                $user->user_email,
                __('تم رفض طلبك كبائع', 'vmp'),
                $message
            );
        }
    }

    /**
     * SendVendorRegisteredNotification functionality helper.
     *
     * @param int $vendorId Description index.
     * @param int $userId Description index.
     * @return void Output payload.
     */
    public function sendVendorRegisteredNotification(int $vendorId, int $userId): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($userId);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_vendor_registered'])) {
            $this->sendEmail(
                $user->user_email,
                __('تم استلام طلب التسجيل', 'vmp'),
                sprintf(__('مرحباً %s، تم استلام طلب تسجيلك كبائع وهو قيد المراجعة.', 'vmp'), $vendor->store_name)
            );
        }

        $adminEmail = $settings['admin_email'] ?? get_option('admin_email');
        $this->sendEmail(
            $adminEmail,
            __('طلب تسجيل بائع جديد', 'vmp'),
            sprintf(__('تم تسجيل بائع جديد: %s (%s)', 'vmp'), $vendor->store_name, $user->user_email)
        );
    }

    /**
     * SendVendorApprovedNotification functionality helper.
     *
     * @param int $vendorId Description index.
     * @return void Output payload.
     */
    public function sendVendorApprovedNotification(int $vendorId): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($vendor->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_vendor_approved'])) {
            $this->sendEmail(
                $user->user_email,
                __('تم قبول طلبك كبائع!', 'vmp'),
                sprintf(__('تهانيناً %s! تم قبول طلبك كبائع. يمكنك الآن البدء في إضافة منتجاتك.', 'vmp'), $vendor->store_name)
            );
        }
    }

    /**
     * SendVendorRejectedNotification functionality helper.
     *
     * @param int $vendorId Description index.
     * @param string $reason Description index.
     * @return void Output payload.
     */
    public function sendVendorRejectedNotification(int $vendorId, string $reason): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($vendor->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_vendor_rejected'])) {
            $this->sendEmail(
                $user->user_email,
                __('تم رفض طلبك كبائع', 'vmp'),
                sprintf(__('نعتذر %s، تم رفض طلبك كبائع. السبب: %s', 'vmp'), $vendor->store_name, $reason)
            );
        }
    }

    /**
     * SendProductApprovedNotification functionality helper.
     *
     * @param int $productId Description index.
     * @param int $vendorId Description index.
     * @return void Output payload.
     */
    public function sendProductApprovedNotification(int $productId, int $vendorId): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($vendor->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_product_approved'])) {
            $product = wc_get_product($productId);
            $productName = $product ? $product->get_name() : '';
            $this->sendEmail(
                $user->user_email,
                __('تم قبول منتجك', 'vmp'),
                sprintf(__('تم قبول منتجك "%s" وهو الآن متاح للبيع.', 'vmp'), $productName)
            );
        }
    }

    /**
     * SendOrderPlacedNotification functionality helper.
     *
     * @param int $parentOrderId Description index.
     * @param int $vendorId Description index.
     * @return void Output payload.
     */
    public function sendOrderPlacedNotification(int $parentOrderId, int $vendorId): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($vendor->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_order_placed'])) {
            $this->sendEmail(
                $user->user_email,
                __('طلب جديد!', 'vmp'),
                sprintf(__('لديك طلب جديد #%d', 'vmp'), $parentOrderId)
            );
        }
    }

    /**
     * SendWithdrawalApprovedNotification functionality helper.
     *
     * @param int $withdrawalId Description index.
     * @param int $vendorId Description index.
     * @param float $amount Description index.
     * @return void Output payload.
     */
    public function sendWithdrawalApprovedNotification(int $withdrawalId, int $vendorId, float $amount): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($vendor->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_withdrawal_approved'])) {
            $this->sendEmail(
                $user->user_email,
                __('تمت الموافقة على طلب السحب', 'vmp'),
                sprintf(__('تمت الموافقة على طلب سحب بقيمة %s', 'vmp'), wc_price($amount))
            );
        }
    }

    /**
     * SendSubscriptionExpiringNotification functionality helper.
     *
     * @param int $subscriptionId Description index.
     * @param int $vendorId Description index.
     * @param string $endDate Description index.
     * @return void Output payload.
     */
    public function sendSubscriptionExpiringNotification(int $subscriptionId, int $vendorId, string $endDate): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($vendor->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_subscription_expiring'])) {
            $this->sendEmail(
                $user->user_email,
                __('اشتراكك على وشك الانتهاء', 'vmp'),
                sprintf(__('اشتراكك سينتهي في %s. يرجى التجديد للاستمرار في التمتع بالمزايا.', 'vmp'), $endDate)
            );
        }
    }

    /**
     * SendOrderCancelledNotification functionality helper.
     *
     * @param int $parentOrderId Description index.
     * @param int $vendorId Description index.
     * @return void Output payload.
     */
    public function sendOrderCancelledNotification(int $parentOrderId, int $vendorId): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($vendor->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_order_cancelled'])) {
            $this->sendEmail(
                $user->user_email,
                __('تم إلغاء طلب', 'vmp'),
                sprintf(__('تم إلغاء الطلب رقم #%d.', 'vmp'), $parentOrderId)
            );
        }
    }

    /**
     * SendCommissionPaidNotification functionality helper.
     *
     * @param int $commissionId Description index.
     * @param int $vendorId Description index.
     * @param float $amount Description index.
     * @return void Output payload.
     */
    public function sendCommissionPaidNotification(int $commissionId, int $vendorId, float $amount): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) return;

        $user = get_userdata($vendor->user_id);
        if (!$user) return;

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_commission_paid'])) {
            $this->sendEmail(
                $user->user_email,
                __('تم صرف عمولتك', 'vmp'),
                sprintf(__('تم إضافة عمولة بقيمة %s إلى رصيدك.', 'vmp'), function_exists('wc_price') ? wc_price($amount) : number_format($amount, 2))
            );
        }
    }
}