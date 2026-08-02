<?php
// Email template: vendor request changes
$req = $payloadForTemplate['request'] ?? null;
$vendor = $payloadForTemplate['vendor'] ?? null;
$admin = $payloadForTemplate['admin'] ?? null;
$message = $payloadForTemplate['message'] ?? '';
$wizard = $payloadForTemplate['wizard_url'] ?? '';
$review = $payloadForTemplate['review_url'] ?? '';
?>
<!doctype html>
<html>
  <body>
    <p>مرحبا <?php echo esc_html($vendor?->display_name ?? ''); ?>,</p>
    <p>طلب البائع رقم #<?php echo intval($req->id ?? 0); ?> يحتاج إلى تعديلات من المشرف: <?php echo esc_html($admin?->display_name ?? ''); ?>.</p>
    <blockquote><?php echo nl2br(esc_html($message)); ?></blockquote>
    <?php if ($wizard): ?>
      <p>تابع التعديلات من هنا: <a href="<?php echo esc_url($wizard); ?>"><?php echo esc_html($wizard); ?></a></p>
    <?php endif; ?>
    <p>لمراجعة الحالة: <a href="<?php echo esc_url($review); ?>"><?php echo esc_html($review); ?></a></p>
  </body>
</html>
