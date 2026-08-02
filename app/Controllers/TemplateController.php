<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Modules\Template;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ErrorResponse;
use VMP\Http\Responses\ApiResponse;
use VMP\Contracts\VendorRepositoryInterface;

/**
 * Class TemplateController
 *
 * [QA 2026-08-02] نُقلت معالجة AJAX للقالب من VMP\Modules\Template
 * (تسجيل add_action مباشر) إلى RouteRegistry في CoreServiceProvider —
 * لتوحيد نمط التسجيل مع بقية الوحدات وإضافة فحص صلاحيات صريح.
 *
 * @package vendor-marketplace
 */
class TemplateController
{
    private Template $templateModule;

    public function __construct(
        private Container $container,
        private VendorRepositoryInterface $vendorRepository
    ) {
        $module = $this->container->make('module_manager')->get_module('template');
        if (!$module instanceof Template) {
            throw new \RuntimeException('Template module غير متاح');
        }
        $this->templateModule = $module;
    }

    /**
     * حفظ إعدادات القالب (بائع معتمد فقط — يعمل على متجره هو)
     */
    public function saveTemplate(): ApiResponse
    {
        if (!is_user_logged_in()) {
            return new ErrorResponse(message: __('يجب تسجيل الدخول', 'vmp'), statusCode: 401);
        }

        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            return new ErrorResponse(message: __('البائع غير موجود', 'vmp'), statusCode: 404);
        }

        // [QA 2026-08-02] فحص صريح: بائع معتمد فقط يمكنه تعديل قالب متجره
        if (!current_user_can('vmp_vendor_products')) {
            return new ErrorResponse(message: __('ليس لديك صلاحية تعديل القالب', 'vmp'), statusCode: 403);
        }

        $template = sanitize_text_field($_POST['template'] ?? 'classic');
        $available = $this->templateModule->get_available_templates();
        if (!isset($available[$template])) {
            $template = 'classic';
        }

        $fontFamily = sanitize_text_field($_POST['font_family'] ?? 'Cairo');
        $availableFonts = $this->templateModule->get_available_fonts();
        if (!isset($availableFonts[$fontFamily])) {
            $fontFamily = 'Cairo';
        }

        $settings = [
            'template' => $template,
            'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '#6366f1'),
            'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '#a5b4fc'),
            'bg_color' => sanitize_hex_color($_POST['bg_color'] ?? '#ffffff'),
            'text_color' => sanitize_hex_color($_POST['text_color'] ?? '#1e1b4b'),
            'font_family' => $fontFamily,
            'button_radius' => max(0, min(50, (int) ($_POST['button_radius'] ?? 8))),
            'show_banner' => !empty($_POST['show_banner']),
            'show_rating' => !empty($_POST['show_rating']),
            'products_per_row' => max(1, min(4, (int) ($_POST['products_per_row'] ?? 3))),
        ];

        if (!empty($_POST['custom_css'])) {
            $subscriptionModule = $this->container->make('module_manager')->get_module('subscription');
            if ($subscriptionModule && !$subscriptionModule->has_feature((int) $vendor->id, 'custom_css')) {
                return new ErrorResponse(message: __('هذه الميزة غير متاحة في خطتك الحالية', 'vmp'), statusCode: 403);
            }
            // [QA 2026-08-02] wp_strip_all_tags بدلاً من wp_kses_post — custom_css سياق CSS وليس HTML
            $settings['custom_css'] = wp_strip_all_tags((string) wp_unslash($_POST['custom_css']));
        }

        update_user_meta((int) $vendor->user_id, 'vmp_template_settings', $settings);

        return new SuccessResponse(message: __('تم حفظ إعدادات القالب بنجاح', 'vmp'));
    }

    /**
     * جلب إعدادات القالب (للبائع الحالي)
     */
    public function getTemplateSettings(): ApiResponse
    {
        if (!is_user_logged_in()) {
            return new ErrorResponse(message: __('يجب تسجيل الدخول', 'vmp'), statusCode: 401);
        }

        $vendor = $this->vendorRepository->findByUserId(get_current_user_id());
        if (!$vendor) {
            return new ErrorResponse(message: __('البائع غير موجود', 'vmp'), statusCode: 404);
        }

        return new SuccessResponse(data: $this->templateModule->get_vendor_template_settings((int) $vendor->id));
    }

    /**
     * قائمة القوالب المتاحة (معلومات عامة — لأي مستخدم مسجل)
     */
    public function getTemplatesList(): ApiResponse
    {
        if (!is_user_logged_in()) {
            return new ErrorResponse(message: __('يجب تسجيل الدخول', 'vmp'), statusCode: 401);
        }

        return new SuccessResponse(data: array_values($this->templateModule->get_available_templates()));
    }
}
