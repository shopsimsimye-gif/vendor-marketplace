<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Events\VendorApproved;

class SendApprovalEmailListener
{
    public function __invoke(VendorApproved $event): void
    {
        $email = $event->request->email ?? null;
        if (!$email) return;
        $subject = __('تمت الموافقة على طلبك كبائع', 'vmp');
        $message = __('مبروك! تمت الموافقة على طلبك كبائع. ستتلقى رابط إعداد المتجر بعد تسجيل الدخول.', 'vmp');
        wp_mail($email, $subject, $message);
    }
}
