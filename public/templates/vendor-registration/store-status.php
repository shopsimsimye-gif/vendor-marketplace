<?php
// Store Setup Status Page (frontend)
if (!is_user_logged_in()) {
    auth_redirect();
}
$nonce = wp_create_nonce('wp_rest');
$rest_base = esc_url_raw(rest_url('vmp/v1'));
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php _e('حالة متجر البائع', 'vmp'); ?></title>
<link rel="stylesheet" href="<?php echo esc_url(trailingslashit(VMP_PLUGIN_URL) . 'assets/css/vendor/store-setup-wizard.css'); ?>" />
</head>
<body class="vmp-store-status">
  <div class="vmp-container">
    <div class="vmp-status-card">
      <h1><?php _e('حالة إعداد متجرك', 'vmp'); ?></h1>
      <div id="vmp-status-message"></div>
      <div id="vmp-status-timeline"></div>
      <div id="vmp-status-actions"></div>
    </div>
  </div>

<script>
  window.VMP_StoreSetup = {
    restBase: '<?php echo $rest_base; ?>',
    nonce: '<?php echo $nonce; ?>',
    pluginUrl: '<?php echo esc_url(trailingslashit(VMP_PLUGIN_URL)); ?>'
  };
</script>
<script type="module" src="<?php echo esc_url(trailingslashit(VMP_PLUGIN_URL) . 'assets/js/vendor/store-status.js'); ?>"></script>
</body>
</html>
