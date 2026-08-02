<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\ProductRepository;
use VMP\Repositories\VendorRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Product
 *
 * Description of administrative platform component Product.
 *
 * @package vendor-marketplace
 */
class Product extends AbstractModule
{
    private ProductRepository $repository;
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
        $this->repository = $this->make(ProductRepository::class);
        $this->vendorRepository = $this->make(VendorRepository::class);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        // تم نقل تسجيل AJAX إلى RouteRegistry في CoreServiceProvider
        // add_action('wp_ajax_vmp_add_product', ...);
        // add_action('wp_ajax_vmp_update_product', ...);
        // add_action('wp_ajax_vmp_delete_product', ...);
        // add_action('wp_ajax_vmp_admin_approve_product', ...);
        // add_action('wp_ajax_vmp_admin_reject_product', ...);
    }

    /**
     * إضافة منتج جديد (مع دعم النشر بدون مراجعة)
     */
    public function ajax_add_product(): void
    {
        try {
            if (!check_ajax_referer('vmp_public_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => __('طلب غير مصرح به (nonce غير صحيح).', 'vmp')]);
            }

            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error(['message' => __('يجب تسجيل الدخول أولاً.', 'vmp')]);
            }

            if (!current_user_can('vmp_vendor_products')) {
                $this->fixVendorCapabilities($user_id);
                if (!current_user_can('vmp_vendor_products')) {
                    wp_send_json_error([
                        'message' => __('ليس لديك صلاحية لإضافة منتج. تأكد من أن حسابك بائع معتمد.', 'vmp'),
                        'debug'   => 'missing_cap: vmp_vendor_products'
                    ]);
                }
            }

            $vendor = $this->vendorRepository->findByUserId($user_id);
            if (!$vendor) {
                wp_send_json_error(['message' => __('البائع غير موجود. يرجى التسجيل كبائع أولاً.', 'vmp')]);
            }
            if ($vendor->status !== 'approved') {
                wp_send_json_error(['message' => __('حساب البائع لم تتم الموافقة عليه بعد.', 'vmp')]);
            }

            $subscription = $this->container->get('module_manager')->get_module('subscription');
            if ($subscription && !$subscription->can_add_product($vendor->id)) {
                wp_send_json_error(['message' => __('لقد وصلت للحد الأقصى من المنتجات في خطتك الحالية.', 'vmp')]);
            }

            // ── ✅ التحقق من خيار "النشر بدون مراجعة" ──
            $settings = get_option('vmp_settings', []);
            $auto_approve = isset($settings['general']['auto_approve_products']) && $settings['general']['auto_approve_products'] === '1';
            $product_status = $auto_approve ? 'publish' : 'pending';
            $vendor_product_status = $auto_approve ? 'approved' : 'pending';

            // ── إنشاء المنتج ──
            $product = new \WC_Product_Simple();
            $product->set_name(sanitize_text_field($_POST['product_name'] ?? ''));
            $product->set_regular_price((float) ($_POST['regular_price'] ?? 0));
            $product->set_sale_price((float) ($_POST['sale_price'] ?? 0));
            $product->set_description(sanitize_textarea_field($_POST['description'] ?? ''));
            $product->set_short_description(sanitize_textarea_field($_POST['short_description'] ?? ''));
            $product->set_sku(sanitize_text_field($_POST['sku'] ?? ''));

            $manage_stock = (isset($_POST['manage_stock']) && $_POST['manage_stock'] === 'yes');
            $product->set_manage_stock($manage_stock);
            if ($manage_stock) {
                $product->set_stock_quantity((int) ($_POST['stock_quantity'] ?? 0));
            }

            if (!empty($_POST['category'])) {
                $product->set_category_ids([(int) $_POST['category']]);
            }
            if (!empty($_POST['image_id'])) {
                $product->set_image_id((int) $_POST['image_id']);
            }

            // ✅ تعيين حالة المنتج بناءً على الإعدادات
            $product->set_status($product_status);
            $product_id = $product->save();

            if (!$product_id) {
                wp_send_json_error(['message' => __('فشل إنشاء المنتج في قاعدة البيانات.', 'vmp')]);
            }

            // ── ربط المنتج بالبائع ──
            $vendor_product_id = $this->repository->create($vendor->id, $product_id, [
                'status'      => $vendor_product_status,
                'is_featured' => !empty($_POST['is_featured']),
            ]);

            if ($vendor_product_id) {
                $this->vendorRepository->updateStats($vendor->id);

                // إطلاق حدث إنشاء المنتج
                $this->container->get('event_manager')->trigger(
                    'vmp_product_created',
                    $vendor_product_id,
                    $product_id,
                    $vendor->id
                );

                // ✅ رسالة نجاح حسب الحالة
                if ($auto_approve) {
                    $message = __('تم نشر المنتج بنجاح وهو متاح للبيع.', 'vmp');
                } else {
                    $message = __('تم إنشاء المنتج بنجاح وهو في انتظار الموافقة.', 'vmp');
                }

                wp_send_json_success([
                    'message'    => $message,
                    'product_id' => $vendor_product_id,
                    'status'     => $vendor_product_status,
                ]);
            } else {
                // فشل الربط، احذف المنتج
                wp_delete_post($product_id, true);
                wp_send_json_error(['message' => __('حدث خطأ أثناء ربط المنتج بحسابك.', 'vmp')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Error $e) {
            wp_send_json_error(['message' => 'خطأ داخلي: ' . $e->getMessage()]);
        }
    }

    /**
     * Ajax Update Product functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_update_product(): void
    {
        try {
            if (!check_ajax_referer('vmp_public_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => __('طلب غير مصرح به (nonce غير صحيح).', 'vmp')]);
            }

            if (!current_user_can('vmp_vendor_products')) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية لتحديث المنتجات.', 'vmp')]);
            }

            $vendor_product_id = (int) ($_POST['vendor_product_id'] ?? 0);
            if (!$vendor_product_id) {
                wp_send_json_error(['message' => __('معرف المنتج غير صالح.', 'vmp')]);
            }

            $vendor_product = $this->repository->find($vendor_product_id);
            if (!$vendor_product) {
                wp_send_json_error(['message' => __('المنتج غير موجود.', 'vmp')]);
            }

            $user_id = get_current_user_id();
            $vendor = $this->vendorRepository->findByUserId($user_id);
            if (!$vendor || $vendor_product->vendor_id != $vendor->id) {
                wp_send_json_error(['message' => __('أنت لا تملك صلاحية تعديل هذا المنتج.', 'vmp')]);
            }

            $product = \wc_get_product($vendor_product->product_id);
            if ($product) {
                $product->set_name(sanitize_text_field($_POST['product_name'] ?? $product->get_name()));
                $product->set_regular_price((float) ($_POST['regular_price'] ?? $product->get_regular_price()));
                $product->set_sale_price((float) ($_POST['sale_price'] ?? $product->get_sale_price()));
                $product->set_description(sanitize_textarea_field($_POST['description'] ?? $product->get_description()));
                $product->set_short_description(sanitize_textarea_field($_POST['short_description'] ?? $product->get_short_description()));

                if (isset($_POST['category']) && !empty($_POST['category'])) {
                    $product->set_category_ids([(int) $_POST['category']]);
                }
                if (isset($_POST['image_id'])) {
                    $product->set_image_id((int) $_POST['image_id']);
                }

                $manage_stock = (isset($_POST['manage_stock']) && $_POST['manage_stock'] === 'yes');
                $product->set_manage_stock($manage_stock);
                if ($manage_stock) {
                    $product->set_stock_quantity((int) ($_POST['stock_quantity'] ?? 0));
                }

                $product->save();
            } else {
                wp_send_json_error(['message' => __('المنتج غير موجود في WooCommerce.', 'vmp')]);
            }

            $this->repository->update($vendor_product_id, [
                'is_featured' => !empty($_POST['is_featured'])
            ]);

            wp_send_json_success(['message' => __('تم تحديث المنتج بنجاح.', 'vmp')]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Error $e) {
            wp_send_json_error(['message' => 'خطأ داخلي: ' . $e->getMessage()]);
        }
    }

    /**
     * Ajax Delete Product functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_delete_product(): void
    {
        try {
            if (!check_ajax_referer('vmp_public_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => __('طلب غير مصرح به (nonce غير صحيح).', 'vmp')]);
            }

            if (!current_user_can('vmp_vendor_products')) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية لحذف المنتجات.', 'vmp')]);
            }

            $vendor_product_id = (int) ($_POST['vendor_product_id'] ?? 0);
            if (!$vendor_product_id) {
                wp_send_json_error(['message' => __('معرف المنتج غير صالح.', 'vmp')]);
            }

            $vendor_product = $this->repository->find($vendor_product_id);
            if (!$vendor_product) {
                wp_send_json_error(['message' => __('المنتج غير موجود.', 'vmp')]);
            }

            $user_id = get_current_user_id();
            $vendor = $this->vendorRepository->findByUserId($user_id);
            if (!$vendor || $vendor_product->vendor_id !== $vendor->id) {
                wp_send_json_error(['message' => __('أنت لا تملك صلاحية حذف هذا المنتج.', 'vmp')]);
            }

            wp_delete_post($vendor_product->product_id, true);
            $this->repository->delete($vendor_product_id);
            $this->vendorRepository->updateStats($vendor->id);

            wp_send_json_success(['message' => __('تم حذف المنتج بنجاح.', 'vmp')]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Error $e) {
            wp_send_json_error(['message' => 'خطأ داخلي: ' . $e->getMessage()]);
        }
    }

    /**
     * Ajax Admin Approve functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_approve(): void
    {
        try {
            if (!check_ajax_referer('vmp_admin_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => __('طلب غير مصرح به (nonce غير صحيح).', 'vmp')]);
            }

            if (!current_user_can('vmp_manage_products')) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية للموافقة على المنتجات.', 'vmp')]);
            }

            $vendor_product_id = (int) ($_POST['vendor_product_id'] ?? $_POST['id'] ?? 0);
            if (!$vendor_product_id) {
                wp_send_json_error(['message' => __('معرف المنتج غير صالح.', 'vmp')]);
            }

            if ($this->repository->approve($vendor_product_id)) {
                $vendor_product = $this->repository->find($vendor_product_id);
                if ($vendor_product) {
                    $product = \wc_get_product($vendor_product->product_id);
                    if ($product) {
                        $product->set_status('publish');
                        $product->save();
                    }
                    $this->container->get('event_manager')->trigger(
                        'vmp_product_approved',
                        $vendor_product_id,
                        $vendor_product->product_id,
                        $vendor_product->vendor_id
                    );
                }
                wp_send_json_success(['message' => __('تم الموافقة على المنتج بنجاح.', 'vmp')]);
            } else {
                wp_send_json_error(['message' => __('فشلت عملية الموافقة، يرجى المحاولة مرة أخرى.', 'vmp')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Error $e) {
            wp_send_json_error(['message' => 'خطأ داخلي: ' . $e->getMessage()]);
        }
    }

    /**
     * Ajax Admin Reject functionality helper.
     *
     * @return void Output payload.
     */
    public function ajax_admin_reject(): void
    {
        try {
            if (!check_ajax_referer('vmp_admin_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => __('طلب غير مصرح به (nonce غير صحيح).', 'vmp')]);
            }

            if (!current_user_can('vmp_manage_products')) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية لرفض المنتجات.', 'vmp')]);
            }

            $vendor_product_id = (int) ($_POST['vendor_product_id'] ?? $_POST['id'] ?? 0);
            if (!$vendor_product_id) {
                wp_send_json_error(['message' => __('معرف المنتج غير صالح.', 'vmp')]);
            }

            if ($this->repository->reject($vendor_product_id)) {
                $this->container->get('event_manager')->trigger('vmp_product_rejected', $vendor_product_id);
                wp_send_json_success(['message' => __('تم رفض المنتج بنجاح.', 'vmp')]);
            } else {
                wp_send_json_error(['message' => __('فشلت عملية الرفض، يرجى المحاولة مرة أخرى.', 'vmp')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Error $e) {
            wp_send_json_error(['message' => 'خطأ داخلي: ' . $e->getMessage()]);
        }
    }

    /**
     * FixVendorCapabilities functionality helper.
     *
     * @param int $user_id Description index.
     * @return void Output payload.
     */
    private function fixVendorCapabilities(int $user_id): void
    {
        $user = new \WP_User($user_id);
        if (in_array('vmp_vendor', $user->roles, true)) {
            $user->add_cap('vmp_vendor_products');
            $user->add_cap('vmp_vendor_orders');
            $user->add_cap('vmp_vendor_withdrawals');
            $user->add_cap('vmp_vendor_reports');
            $user->add_cap('vmp_vendor_subscription');
        } else {
            $vendor = $this->vendorRepository->findByUserId($user_id);
            if ($vendor && $vendor->status === 'approved') {
                $user->add_role('vmp_vendor');
                $user->add_cap('vmp_vendor_products');
                $user->add_cap('vmp_vendor_orders');
                $user->add_cap('vmp_vendor_withdrawals');
                $user->add_cap('vmp_vendor_reports');
                $user->add_cap('vmp_vendor_subscription');
            }
        }
    }
}