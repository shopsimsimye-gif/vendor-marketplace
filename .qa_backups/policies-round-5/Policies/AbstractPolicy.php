<?php
namespace VMP\Policies;

defined('ABSPATH') || exit;

/**
 * Class AbstractPolicy
 *
 * Description of administrative platform component AbstractPolicy.
 *
 * @package vendor-marketplace
 */
abstract class AbstractPolicy
{
    /**
     * الحصول على معرف البائع الحالي.
     * يُفترض أن يعتمد على تسجيل الدخول أو الجلسة.
     * 
     * @return int
     */
    protected function currentVendorId(): int
    {
        // هذه وظيفة مؤقتة (Placeholder).
        // في المستقبل يمكن استبدالها بـ VendorService أو AuthManager.
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return 0;
        }

        // نفترض هنا أن vendor_id يكون متوفراً أو يمكن جلبه.
        // بشكل مبسط:
        return (int) get_user_meta($user_id, 'vmp_vendor_id', true);
    }

    /**
     * هل المستخدم الحالي أدمن؟
     */
    protected function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }
}
