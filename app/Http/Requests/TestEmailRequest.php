<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class TestEmailRequest
 *
 * [QA 2026-08-05] Phase C — طلب اختبار إعدادات البريد الإلكتروني (admin).
 *
 * @package vendor-marketplace
 */
class TestEmailRequest extends AbstractRequest
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
