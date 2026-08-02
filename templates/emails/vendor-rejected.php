<?php
// Email template: vendor rejected
$req = $payloadForTemplate['request'] ?? null;
$vendor = $payloadForTemplate['vendor'] ?? null;
$admin = $payloadForTemplate['admin'] ?? null;
$reason = $payloadForTemplate['message'] ?? '';
$review = $payloadForTemplate['review_url'] ?? '';
?>
<!doctype html>
<html>
  <body>
    <p>مرحبا <?php echo esc_html($vendor?->display_name ?? ''); ?>,</p>
    <p>نأسف لإبلاغك أن طلب البائع رقم #<?php echo intval($req->id ?? 0); ?> قد تم رفضه.</p>
    <p>السبب المقدم من المشرف: <blockquote><?php echo nl2br(esc_html($reason)); ?></blockquote></p>
    <p>للمراجعة: <a href="<?php echo esc_url($review); ?>"><?php echo esc_html($review); ?></a></p>
  </body>
</html>
