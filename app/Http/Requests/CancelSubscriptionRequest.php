<?php
/**
 * CancelSubscriptionRequest — طلب إلغاء اشتراك
 *
 * @package VMP\Http\Requests
 * @since 3.0.0
 */

namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Container;

class CancelSubscriptionRequest extends AbstractRequest
{
    /**
     * التحقق من الصلاحية:
     * - المدير (Manager) يملك صلاحية كاملة عبر vmp_manage_subscriptions
     * - البائع يجب أن يكون معتمداً لإلغاء اشتراكه الخاص فقط
     */
    public function authorize(): bool
    {
        // المدير يملك صلاحية إدارة الاشتراكات
        if (current_user_can('vmp_manage_subscriptions')) {
            return true;
        }

        // البائع: يجب تسجيل الدخول والتحقق من حالة الاعتماد
        if (!is_user_logged_in()) {
            return false;
        }

        try {
            $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
            $vendor = $vendorRepo->findByUserId(get_current_user_id());

            return $vendor && $vendor->status === 'approved';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * القواعد: لا توجد مدخلات مطلوبة
     * (الإلغاء يُنفذ على اشتراك البائع الحالي)
     */
    protected function rules(): array
    {
        return [];
    }
}
