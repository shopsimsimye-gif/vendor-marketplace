<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\VendorRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Notification
 *
 * Description of administrative platform component Notification.
 *
 * @package vendor-marketplace
 */
class Notification extends AbstractModule
{
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
        $this->vendorRepository = $this->make(VendorRepository::class);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        $event_manager = $this->container->get('event_manager');
        $event_manager->add_listener('vmp_vendor_registered', [$this, 'on_vendor_registered']);
        $event_manager->add_listener('vmp_vendor_approved', [$this, 'on_vendor_approved']);
        $event_manager->add_listener('vmp_vendor_rejected', [$this, 'on_vendor_rejected']);
        $event_manager->add_listener('vmp_product_approved', [$this, 'on_product_approved']);
        $event_manager->add_listener('vmp_order_placed', [$this, 'on_order_placed']);
        $event_manager->add_listener('vmp_withdrawal_approved', [$this, 'on_withdrawal_approved']);
        $event_manager->add_listener('vmp_subscription_expiring', [$this, 'on_subscription_expiring']);
    }

    /**
     * On Vendor Registered functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $user_id Description index.
     * @return void Output payload.
     */
    public function on_vendor_registered(int $vendor_id, int $user_id): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_vendor_registered'])) {
            wp_mail(
                $user->user_email,
                __('تم استلام طلب التسجيل', 'vmp'),
                sprintf(__('مرحباً %s، تم استلام طلب تسجيلك كبائع وهو قيد المراجعة.', 'vmp'), $vendor->store_name)
            );
        }

        $admin_email = $settings['admin_email'] ?? get_option('admin_email');
        wp_mail(
            $admin_email,
            __('طلب تسجيل بائع جديد', 'vmp'),
            sprintf(__('تم تسجيل بائع جديد: %s (%s)', 'vmp'), $vendor->store_name, $user->user_email)
        );
    }

    /**
     * On Vendor Approved functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return void Output payload.
     */
    public function on_vendor_approved(int $vendor_id): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) {
            return;
        }

        $user = get_userdata($vendor->user_id);
        if (!$user) {
            return;
        }

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_vendor_approved'])) {
            wp_mail(
                $user->user_email,
                __('تم قبول طلبك كبائع!', 'vmp'),
                sprintf(__('تهانيناً %s! تم قبول طلبك كبائع. يمكنك الآن البدء في إضافة منتجاتك.', 'vmp'), $vendor->store_name)
            );
        }
    }

    /**
     * On Vendor Rejected functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param string $reason Description index.
     * @return void Output payload.
     */
    public function on_vendor_rejected(int $vendor_id, string $reason): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) {
            return;
        }

        $user = get_userdata($vendor->user_id);
        if (!$user) {
            return;
        }

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_vendor_rejected'])) {
            wp_mail(
                $user->user_email,
                __('تم رفض طلبك كبائع', 'vmp'),
                sprintf(__('نعتذر %s، تم رفض طلبك كبائع. السبب: %s', 'vmp'), $vendor->store_name, $reason)
            );
        }
    }

    /**
     * On Product Approved functionality helper.
     *
     * @param int $vendor_product_id Description index.
     * @param int $product_id Description index.
     * @param int $vendor_id Description index.
     * @return void Output payload.
     */
    public function on_product_approved(int $vendor_product_id, int $product_id, int $vendor_id): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) {
            return;
        }

        $user = get_userdata($vendor->user_id);
        if (!$user) {
            return;
        }

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_product_approved'])) {
            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_name() : '';
            wp_mail(
                $user->user_email,
                __('تم قبول منتجك', 'vmp'),
                sprintf(__('تم قبول منتجك "%s" وهو الآن متاح للبيع.', 'vmp'), $product_name)
            );
        }
    }

    /**
     * On Order Placed functionality helper.
     *
     * @param int $vendor_order_id Description index.
     * @param int $parent_order_id Description index.
     * @param int $vendor_id Description index.
     * @return void Output payload.
     */
    public function on_order_placed(int $vendor_order_id, int $parent_order_id, int $vendor_id): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) {
            return;
        }

        $user = get_userdata($vendor->user_id);
        if (!$user) {
            return;
        }

        $settings = get_option('vmp_notification_settings', []);
        if (!empty($settings['email_order_placed'])) {
            wp_mail(
                $user->user_email,
                __('طلب جديد!', 'vmp'),
                sprintf(__('لديك طلب جديد #%d', 'vmp'), $parent_order_id)
            );
        }
    }

    /**
     * On Withdrawal Approved functionality helper.
     *
     * @param int $withdrawal_id Description index.
     * @param int $vendor_id Description index.
     * @param float $amount Description index.
     * @return void Output payload.
     */
    public function on_withdrawal_approved(int $withdrawal_id, int $vendor_id, float $amount): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) {
            return;
        }

        $user = get_userdata($vendor->user_id);
        if (!$user) {
            return;
        }

        wp_mail(
            $user->user_email,
            __('تم الموافقة على السحب', 'vmp'),
            sprintf(__('تم الموافقة على طلب سحب بقيمة %s', 'vmp'), wc_price($amount))
        );
    }

    /**
     * On Subscription Expiring functionality helper.
     *
     * @param int $subscription_id Description index.
     * @param int $vendor_id Description index.
     * @param string $end_date Description index.
     * @return void Output payload.
     */
    public function on_subscription_expiring(int $subscription_id, int $vendor_id, string $end_date): void
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        if (!$vendor) {
            return;
        }

        $user = get_userdata($vendor->user_id);
        if (!$user) {
            return;
        }

        wp_mail(
            $user->user_email,
            __('اشتراكك على وشك الانتهاء', 'vmp'),
            sprintf(__('اشتراكك ينتهي في %s. يرجى التجديد لتجنب انقطاع الخدمة.', 'vmp'), $end_date)
        );
    }
}
