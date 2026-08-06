<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class SaveSettingsRequest
 *
 * [QA 2026-08-05] Phase C — طلب حفظ إعدادات الإضافة (admin).
 * يُرسل عبر AJAX من صفحة الإعدادات بحقل nonce 'vmp_admin_nonce'.
 *
 * @package vendor-marketplace
 */
class SaveSettingsRequest extends AbstractRequest
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
     * قواعد التحقق: vmp_settings يجب أن تكون مصفوفة.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [
            'vmp_settings' => ['array'],
            'vmp_settings.cache' => ['array'],
        ];
    }

    /**
     * تسمية الحقول.
     *
     * @return array
     */
    protected function attributes(): array
    {
        return [
            'vmp_settings' => __('الإعدادات', 'vmp'),
        ];
    }
}
