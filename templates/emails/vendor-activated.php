<?php
// Email template: vendor activated
// Variables available: $payloadForTemplate (array) containing keys described in design
// Expect: 'vendor','request','session','admin','review_url','wizard_url','status_url','message'
$req = $payloadForTemplate['request'] ?? null;
$vendor = $payloadForTemplate['vendor'] ?? null;
$admin = $payloadForTemplate['admin'] ?? null;
$wizard = $payloadForTemplate['wizard_url'] ?? '';
$review = $payloadForTemplate['review_url'] ?? '';
$status = $payloadForTemplate['status_url'] ?? '';
$message = $payloadForTemplate['message'] ?? '';

$vendorName = $vendor?->display_name ?? $vendor?->user_login ?? $vendor?->name ?? '';
$adminName = $admin?->display_name ?? $admin?->user_login ?? $admin?->name ?? '';
$requestId = intval($req->id ?? $payloadForTemplate['request_id'] ?? 0);
$storeName = $req->store_name ?? $payloadForTemplate['store_name'] ?? '';

$escHtml = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$escUrl = static function ($value): string {
    $url = trim((string) $value);
    if ($url === '') {
        return '';
    }

    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<!doctype html>
<html lang="ar" dir="rtl">
  <body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <div style="max-width: 640px; margin: 0 auto; padding: 24px; border: 1px solid #e5e7eb; border-radius: 12px;">
      <h2 style="margin-bottom: 12px; color: #111827;">تم تفعيل طلبك بنجاح</h2>
      <p>مرحبا <strong><?php echo $escHtml($vendorName ?: 'بائعنا العزيز'); ?></strong>،</p>
      <p>تمت الموافقة على طلب البائع رقم #<?php echo (int) $requestId; ?> بنجاح، وقد تم تفعيل الحساب والمتجر الخاص بك من قبل <strong><?php echo $escHtml($adminName ?: 'المشرف'); ?></strong>.</p>

      <?php if (!empty($storeName)) : ?>
        <p>المتجر المتوقع: <strong><?php echo $escHtml($storeName); ?></strong></p>
      <?php endif; ?>

      <?php if (!empty($message)) : ?>
        <p><?php echo nl2br($escHtml($message)); ?></p>
      <?php endif; ?>

      <?php if ($wizard) : ?>
        <p style="margin: 16px 0;">
          <a href="<?php echo $escUrl($wizard); ?>" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px;">
            متابعة إعداد المتجر
          </a>
        </p>
      <?php endif; ?>

      <?php if ($review) : ?>
        <p>للمراجعة عبر لوحة المشرف: <a href="<?php echo $escUrl($review); ?>">فتح الطلب</a></p>
      <?php endif; ?>

      <?php if ($status) : ?>
        <p>يمكنك متابعة الحالة عبر: <a href="<?php echo $escUrl($status); ?>">رابط الحالة</a></p>
      <?php endif; ?>

      <p>مع تحيات فريقنا.</p>
    </div>
  </body>
</html>
