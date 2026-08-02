<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\WhatsappService;
use VMP\Http\Requests\TrackWhatsappClickRequest;
use VMP\Http\Requests\SaveWhatsappSettingsRequest;
use VMP\Http\Requests\GetWhatsappStatsRequest;
use VMP\Http\Requests\AdminWhatsappSettingsRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ApiResponse;
use VMP\Exceptions\ServiceException;

/**
 * Class WhatsappController
 *
 * Description of administrative platform component WhatsappController.
 *
 * @package vendor-marketplace
 */
class WhatsappController extends BaseController
{
    public function __construct(
        private WhatsappService $whatsappService
    ) {}

    /**
     * تتبع نقرة على زر واتساب (للجميع — مسجلين وغير مسجلين)
     */
    public function trackClick(TrackWhatsappClickRequest $request): ApiResponse
    {
        $data = $request->validated();

        $this->whatsappService->trackClick(
            vendorId:  (int) ($data['vendor_id'] ?? 0),
            productId: (int) ($data['product_id'] ?? 0),
            pageUrl:   sanitize_url($data['page_url'] ?? ''),
            clickType: sanitize_text_field($data['click_type'] ?? 'button'),
            userAgent: sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            referrer:  sanitize_url($_SERVER['HTTP_REFERER'] ?? '')
        );

        return new SuccessResponse(message: __('تم تسجيل النقرة', 'vmp'));
    }

    /**
     * حفظ إعدادات واتساب (للبائع)
     */
    public function saveSettings(SaveWhatsappSettingsRequest $request): ApiResponse
    {
        $data = $request->validated();

        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $this->whatsappService->saveSettings(
            $vendorId,
            sanitize_text_field($data['whatsapp_number'] ?? ''),
            sanitize_textarea_field($data['whatsapp_message'] ?? '')
        );

        return new SuccessResponse(message: __('تم حفظ إعدادات واتساب بنجاح', 'vmp'));
    }

    /**
     * جلب إحصائيات واتساب الخاصة بالبائع (للبائع)
     */
    public function getStats(GetWhatsappStatsRequest $request): ApiResponse
    {
        $data = $request->validated();

        $userId = get_current_user_id();
        $vendorId = (int) get_user_meta($userId, 'vmp_vendor_id', true);

        if (!$vendorId) {
            throw new ServiceException(__('البائع غير موجود', 'vmp'));
        }

        $stats = $this->whatsappService->getVendorStats($vendorId, $data['period']);

        return new SuccessResponse(data: $stats);
    }

    /**
     * إعدادات واتساب العامة في لوحة المشرف
     */
    public function adminSettings(AdminWhatsappSettingsRequest $request): ApiResponse
    {
        $data = $request->validated();

        if (isset($data['show_on_product'])) {
            update_option('vmp_whatsapp_show_on_product', (bool) $data['show_on_product']);
        }
        if (isset($data['default_message'])) {
            update_option('vmp_whatsapp_default_message', sanitize_textarea_field($data['default_message']));
        }

        return new SuccessResponse(message: __('تم حفظ إعدادات واتساب', 'vmp'));
    }

    /**
     * إحصائيات واتساب الإجمالية (للمشرف)
     */
    public function adminGetStats(AdminWhatsappSettingsRequest $request): ApiResponse
    {
        $data = $request->validated();
        $period = sanitize_text_field($data['period'] ?? 'month');
        $stats = $this->whatsappService->getAdminStats($period);

        return new SuccessResponse(data: $stats);
    }

    /**
     * إحصائيات واتساب لبائع محدد (للمشرف)
     */
    public function adminGetVendorStats(AdminWhatsappSettingsRequest $request): ApiResponse
    {
        $data = $request->validated();
        $vendorId = (int) ($data['vendor_id'] ?? 0);
        $period   = sanitize_text_field($data['period'] ?? 'month');
        $stats = $this->whatsappService->getVendorStats($vendorId, $period);

        return new SuccessResponse(data: $stats);
    }

    /**
     * بيانات الرسم البياني لنقرات واتساب (للمشرف)
     */
    public function adminGetChart(AdminWhatsappSettingsRequest $request): ApiResponse
    {
        $data = $request->validated();
        $days = (int) ($data['days'] ?? 30);
        $chart = $this->whatsappService->getChartData($days);

        return new SuccessResponse(data: ['chart_data' => $chart]);
    }
}
