<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class MarkAllNoticesReadRequest
 *
 * [QA 2026-08-05] Phase C — طلب تحديد جميع إشعارات لوحة البائع كمقروءة.
 *
 * @package vendor-marketplace
 */
class MarkAllNoticesReadRequest extends AbstractRequest
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
     * قواعد التحقق: لا مدخلات مطلوبة.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [];
    }
}
