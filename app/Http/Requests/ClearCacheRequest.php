<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class ClearCacheRequest
 *
 * [QA 2026-08-06] — طلب مسح كاش الإضافة من تبويب «التخزين المؤقت» في الإعدادات.
 * يرسل عبر AJAX بحقل nonce 'vmp_admin_nonce'.
 *
 * @package vendor-marketplace
 */
class ClearCacheRequest extends AbstractRequest
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
     * قواعد التحقق: لا توجد حقول مطلوبة.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [];
    }
}
