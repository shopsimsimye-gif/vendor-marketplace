<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class MarkNoticeReadRequest
 *
 * [QA 2026-08-05] Phase C — طلب تحديد إشعار لوحة بائع كمقروء.
 *
 * @package vendor-marketplace
 */
class MarkNoticeReadRequest extends AbstractRequest
{
    /**
     * تحقق الصلاحية: يشترط أن يكون المستخدم مسجلاً دخولاً.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return is_user_logged_in();
    }

    /**
     * قواعد التحقق: notice_id مطلوب.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [
            'notice_id' => ['required'],
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
            'notice_id' => __('معرف الإشعار', 'vmp'),
        ];
    }
}
