<?php
/* Template: Status Dashboard */
$user_id = get_current_user_id();
$repo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository();
$request = $repo->findByUser($user_id);
$status = $request->status ?? 'guest';
?>
<div class="vmp-status-dashboard">
  <h2><?php _e('حالة طلبك كبائع', 'vmp'); ?></h2>
  <ul>
    <li><?php _e('تم إنشاء الحساب', 'vmp'); ?></li>
    <li><?php _e('تم التحقق من البريد والهاتف', 'vmp'); ?></li>
    <li><?php _e('تم إرسال الطلب', 'vmp'); ?></li>
    <li><?php echo esc_html($status); ?></li>
  </ul>
</div>
