<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class GetSettingsRequest
 *
 * [QA 2026-08-05] Phase C — طلب جلب الإعدادات الحالية (admin, للتطوير).
 *
 * @package vendor-marketplace
 */
class GetSettingsRequest extends AbstractRequest
{
    /**
     * تحقق الصلاحية: يتطلب صلاحية إدارة الإعدادات.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_settings');
    }

    /**
     * قواعد التحقق: لا مدخلات مطلوبة.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [];
    }
}
