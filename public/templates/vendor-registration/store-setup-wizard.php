<?php
// Store Setup Wizard frontend template
if (!is_user_logged_in()) {
    auth_redirect();
}
$nonce = wp_create_nonce('wp_rest');
$rest_base = esc_url_raw(rest_url('vmp/v1'));
$session_uuid = '';
// try to read session uuid from query param or localStorage via JS
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php _e('إعداد المتجر', 'vmp'); ?></title>
<link rel="stylesheet" href="<?php echo esc_url(trailingslashit(VMP_PLUGIN_URL) . 'assets/css/vendor/store-setup-wizard.css'); ?>" />
</head>
<body class="vmp-store-setup">
  <div id="vmp-store-setup-root" class="vmp-container">
    <div class="vmp-wizard">
      <header class="vmp-wizard-header">
        <h1><?php _e('معالج إعداد المتجر', 'vmp'); ?></h1>
        <div class="vmp-wizard-meta">
          <div id="vmp-wizard-progress" class="vmp-progress"></div>
          <div id="vmp-autosave-status" class="vmp-autosave-status">
            <span id="vmp-autosave-indicator" class="status-dot">○</span>
            <span id="vmp-autosave-label">غير محفوظ</span>
            <span id="vmp-last-saved" class="vmp-last-saved"></span>
          </div>
        </div>
      </header>
      <main id="vmp-wizard-main" class="vmp-wizard-main">
        <!-- wizard will be rendered here by JS -->
      </main>
    </div>
  </div>

  <div id="vmp-session-overlay" class="vmp-session-overlay" style="display:none">
    <div class="vmp-session-overlay-inner">
      <h2><?php _e('انتهت صلاحية جلسة إعداد المتجر', 'vmp'); ?></h2>
      <p><?php _e('تمت انتهاء صلاحية جلسة الإعداد. يمكنك بدء جلسة جديدة للاستمرار في إعداد متجرك.', 'vmp'); ?></p>
      <button id="vmp-start-new-session" class="button"><?php _e('بدء جلسة جديدة', 'vmp'); ?></button>
    </div>
  </div>

<script>
  window.VMP_StoreSetup = {
    restBase: '<?php echo $rest_base; ?>',
    nonce: '<?php echo $nonce; ?>',
    pluginUrl: '<?php echo esc_url(trailingslashit(VMP_PLUGIN_URL)); ?>',
    debug: <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>
  };
</script>
<script type="module" src="<?php echo esc_url(trailingslashit(VMP_PLUGIN_URL) . 'assets/js/vendor/store-setup-wizard.js'); ?>"></script>
</body>
</html>
