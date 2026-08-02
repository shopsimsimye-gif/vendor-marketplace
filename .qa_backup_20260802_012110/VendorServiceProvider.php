<?php
namespace VMP\Modules\Vendor;

defined('ABSPATH') || exit;

use VMP\Providers\ServiceProvider;
use VMP\Services\VendorRegistrationService;
use VMP\Controllers\VendorRegistrationController;
use VMP\Core\ViewRenderer;
use VMP\Core\Logger;

/**
 * موفر خدمة وحدة البائعين - تسجيل الخدمات والتحكم
 */
class VendorServiceProvider extends ServiceProvider
{
    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        // تسجيل خدمة تسجيل البائعين
        $this->container->singleton(
            VendorRegistrationService::class,
            fn(): VendorRegistrationService => new VendorRegistrationService()
        );

        // تسجيل المتحكم
        $this->container->singleton(
            VendorRegistrationController::class,
            fn(): VendorRegistrationController => new VendorRegistrationController(
                $this->container->make(\VMP\Services\VendorRegistrationService::class),
                $this->container->make(Logger::class),
                $this->container->make(ViewRenderer::class)
            )
        );
    }

    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        // إضافة نقطة نهاية (endpoint) لصفحة تسجيل البائعين
        add_action('init', [$this, 'registerVendorRegistrationEndpoint']);
        
        // تسجيل مسار REST API للصفحة العامة
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        
        // إضافة نص برمجي (script) ونمط (style) للصفحة
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // معالجات AJAX
        $this->registerAjaxHandlers();
        
        // إضافة علامة تبويب الإعدادات
        add_filter('vmp_admin_settings_tabs', [$this, 'addRegistrationSettingsTab']);
        add_action('vmp_admin_settings_tab_vendor_registration', [$this, 'renderRegistrationSettingsTab']);
        add_action('vmp_admin_settings_save_vendor_registration', [$this, 'saveRegistrationSettings']);
    }

    /**
     * تسجيل نقطة نهاية صفحة التسجيل
     */
    public function registerVendorRegistrationEndpoint(): void
    {
        add_rewrite_endpoint('become-vendor', EP_ROOT | EP_PAGES);
        add_rewrite_rule('^become-vendor/?$', 'index.php?page_id=' . $this->getRegistrationPageId(), 'top');
    }

    /**
     * تسجيل مسارات REST API
     */
    public function registerRestRoutes(): void
    {
        $controller = $this->container->make(VendorRegistrationController::class);
        
        register_rest_route('vmp/v1', '/vendor-register/step1', [
            'methods'  => 'POST',
            'callback' => [$controller, 'step1VerifyUser'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('vmp/v1', '/vendor-register/step2', [
            'methods'  => 'POST',
            'callback' => [$controller, 'step2StoreData'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('vmp/v1', '/vendor-register/check-slug', [
            'methods'  => 'POST',
            'callback' => [$controller, 'checkSlugAvailability'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('vmp/v1', '/vendor-register/upload-media', [
            'methods'  => 'POST',
            'callback' => [$controller, 'uploadMedia'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('vmp/v1', '/vendor-register/submit', [
            'methods'  => 'POST',
            'callback' => [$controller, 'submitRequest'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('vmp/v1', '/vendor-register/status', [
            'methods'  => 'POST',
            'callback' => [$controller, 'checkRequestStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * تحميل الأصول (CSS/JS)
     */
    public function enqueueAssets(): void
    {
        if (!$this->isVendorRegistrationPage()) {
            return;
        }

        $assetsUrl = VMP_PLUGIN_URL . 'public/assets/';
        $version = VMP_VERSION;

        // CSS
        wp_enqueue_style(
            'vmp-vendor-registration',
            $assetsUrl . 'css/vendor-registration.css',
            [],
            $version
        );

        // JS
        wp_enqueue_script(
            'vmp-vendor-registration',
            $assetsUrl . 'js/vendor-registration.js',
            ['jquery', 'media-upload', 'wp-util'],
            $version,
            true
        );

        // ترجمة البيانات للـ JavaScript
        $registrationPageId = $this->getRegistrationPageId();
        $settings = $this->container->make(\VMP\Services\VendorRegistrationService::class)->getSettings();

        wp_localize_script('vmp-vendor-registration', 'vmpVendorReg', [
            'ajaxUrl'           => admin_url('admin-ajax.php'),
            'restUrl'           => rest_url('vmp/v1/'),
            'nonce'             => wp_create_nonce('vmp_vendor_registration_nonce'),
            'isLoggedIn'        => is_user_logged_in(),
            'currentUser'       => is_user_logged_in() ? [
                'id'            => get_current_user_id(),
                'email'         => wp_get_current_user()->user_email,
                'display_name'  => wp_get_current_user()->display_name,
                'first_name'    => wp_get_current_user()->first_name,
                'last_name'     => wp_get_current_user()->last_name,
            ] : null,
            'settings'          => [
                'termsUrl'              => $this->container->make(\VMP\Services\VendorRegistrationService::class)->getTermsPageUrl(),
                'manualApprovalEnabled' => $this->container->make(\VMP\Services\VendorRegistrationService::class)->isManualApprovalEnabled(),
            ],
            'messages'          => [
                'slugChecking'    => __('جاري التحقق من الرابط...', 'vmp'),
                'slugAvailable'   => __('الرابط متاح ✓', 'vmp'),
                'slugTaken'       => __('الرابط مستخدم ✗', 'vmp'),
                'uploading'       => __('جاري الرفع...', 'vmp'),
                'uploadError'     => __('فشل رفع الملف', 'vmp'),
                'submitting'      => __('جاري الإرسال...', 'vmp'),
                'submitSuccess'   => __('تم الإرسال بنجاح!', 'vmp'),
                'submitError'     => __('حدث خطأ، يرجى المحاولة مرة أخرى', 'vmp'),
                'termsRequired'   => __('يجب الموافقة على الشروط والأحكام', 'vmp'),
                'fieldRequired'   => __('هذا الحقل مطلوب', 'vmp'),
                'invalidEmail'    => __('بريد إلكتروني غير صحيح', 'vmp'),
                'invalidPhone'    => __('رقم هاتف غير صحيح', 'vmp'),
            ],
            'i18n'              => [
                'step1'         => __('الخطوة 1: البيانات الأساسية', 'vmp'),
                'step2'         => __('الخطوة 2: بيانات المتجر', 'vmp'),
                'step3'         => __('الخطوة 3: الشروط والإرسال', 'vmp'),
                'next'          => __('التالي', 'vmp'),
                'prev'          => __('السابق', 'vmp'),
                'submit'        => __('إرسال الطلب', 'vmp'),
                'loading'       => __('جاري التحميل...', 'vmp'),
            ],
        ]);
    }

    /**
     * تسجيل معالجات AJAX
     */
    private function registerAjaxHandlers(): void
    {
        $controller = $this->container->make(VendorRegistrationController::class);

        // AJAX للمستخدمين المسجلين
        add_action('wp_ajax_vmp_vendor_register_step1', [$controller, 'step1VerifyUser']);
        add_action('wp_ajax_vmp_vendor_register_step2', [$controller, 'step2StoreData']);
        add_action('wp_ajax_vmp_check_store_slug', [$controller, 'checkSlugAvailability']);
        add_action('wp_ajax_vmp_vendor_upload_media', [$controller, 'uploadMedia']);
        add_action('wp_ajax_vmp_vendor_register_submit', [$controller, 'submitRequest']);
        add_action('wp_ajax_vmp_vendor_check_status', [$controller, 'checkRequestStatus']);

        // AJAX للزوار (اختياري - في حالة التسجيل بدون تسجيل دخول)
        add_action('wp_ajax_nopriv_vmp_vendor_register_step1', [$controller, 'step1VerifyUser']);
        add_action('wp_ajax_nopriv_vmp_vendor_register_step2', [$controller, 'step2StoreData']);
        add_action('wp_ajax_nopriv_vmp_check_store_slug', [$controller, 'checkSlugAvailability']);
        add_action('wp_ajax_nopriv_vmp_vendor_upload_media', [$controller, 'uploadMedia']);
        add_action('wp_ajax_nopriv_vmp_vendor_register_submit', [$controller, 'submitRequest']);
        add_action('wp_ajax_nopriv_vmp_vendor_check_status', [$controller, 'checkRequestStatus']);
    }

    /**
     * إضافة تبويب إعدادات التسجيل
     */
    public function addRegistrationSettingsTab(array $tabs): array
    {
        $tabs['vendor_registration'] = [
            'label' => __('تسجيل البائعين', 'vmp'),
            'icon'  => 'dashicons-store',
        ];
        return $tabs;
    }

    /**
     * عرض محتوى تبويب إعدادات التسجيل
     */
    public function renderRegistrationSettingsTab(): void
    {
        $service = $this->container->make(VendorRegistrationService::class);
        // نعرض نفس حقول الإعدادات، لكن الخدمة الآن تقرأ من vmp_settings['registration']
        $service->renderSettingsFields();
    }

    /**
     * حفظ إعدادات التسجيل
     */
    public function saveRegistrationSettings(): void
    {
        if (!current_user_can('vmp_manage_settings')) {
            return;
        }

        // توحيد التحقق مع النظام العام (admin_settings_save / vmp_admin_settings_nonce)
        check_admin_referer('vmp_admin_settings_save', 'vmp_admin_settings_nonce');

        // الإعدادات تأتي ضمن vmp_settings[registration] من النموذج الموحد
        $settings = isset($_POST['vmp_settings']['registration']) && is_array($_POST['vmp_settings']['registration'])
            ? $_POST['vmp_settings']['registration']
            : [];

        if (!empty($settings)) {
            $old_settings = get_option('vmp_settings', []);
            $old_settings['registration'] = array_merge($old_settings['registration'] ?? [], $settings);
            update_option('vmp_settings', $old_settings);
        }
    }

    /**
     * التحقق مما إذا كانت الصفحة الحالية هي صفحة تسجيل البائعين
     */
    private function isVendorRegistrationPage(): bool
    {
        // التحقق من الـ shortcode
        global $post;
        if ($post && has_shortcode($post->post_content, 'vmp_vendor_registration')) {
            return true;
        }

        // التحقق من قالب الصفحة
        if (is_page_template('vendor-registration.php')) {
            return true;
        }

        // التحقق من الـ endpoint
        if (get_query_var('become-vendor')) {
            return true;
        }

        // التحقق من الـ page ID في الإعدادات
        $settings = $this->container->make(VendorRegistrationService::class)->getSettings();
        $pageId = $settings['registration_page_id'] ?? 0;
        
        if ($pageId && is_page($pageId)) {
            return true;
        }

        return false;
    }

    /**
     * الحصول على معرف صفحة التسجيل
     */
    private function getRegistrationPageId(): int
    {
        $settings = $this->container->make(VendorRegistrationService::class)->getSettings();
        return $settings['registration_page_id'] ?? 0;
    }
}