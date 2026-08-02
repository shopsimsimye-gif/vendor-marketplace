<?php
/**
 * VMP Settings Page
 */
defined('ABSPATH') || exit;

if (!current_user_can('manage_options') && !current_user_can('vmp_manage_settings')) {
    wp_die(__('عذراً، غير مسموح لك الوصول إلى هذه الصفحة.', 'vmp'));
}

// Handle form submission
if (isset($_POST['vmp_settings_nonce']) && wp_verify_nonce($_POST['vmp_settings_nonce'], 'vmp_save_settings')) {
    $site_key = sanitize_text_field($_POST['vmp_recaptcha_site_key'] ?? '');
    $secret_key = sanitize_text_field($_POST['vmp_recaptcha_secret_key'] ?? '');
    update_option('vmp_recaptcha_site_key', $site_key);
    update_option('vmp_recaptcha_secret_key', $secret_key);
    echo '<div class="updated notice is-dismissible"><p>' . __('تم حفظ الإعدادات بنجاح.', 'vmp') . '</p></div>';
}

$site_key = esc_attr(get_option('vmp_recaptcha_site_key', ''));
$secret_key = esc_attr(get_option('vmp_recaptcha_secret_key', ''));
?>
<div class="wrap">
  <h1><?php _e('إعدادات VMP', 'vmp'); ?></h1>
  <form method="post">
    <?php wp_nonce_field('vmp_save_settings', 'vmp_settings_nonce'); ?>
    <table class="form-table">
      <tr>
        <th scope="row"><label for="vmp_recaptcha_site_key">reCAPTCHA Site Key</label></th>
        <td><input name="vmp_recaptcha_site_key" type="text" id="vmp_recaptcha_site_key" value="<?php echo $site_key; ?>" class="regular-text"/></td>
      </tr>
      <tr>
        <th scope="row"><label for="vmp_recaptcha_secret_key">reCAPTCHA Secret Key</label></th>
        <td><input name="vmp_recaptcha_secret_key" type="text" id="vmp_recaptcha_secret_key" value="<?php echo $secret_key; ?>" class="regular-text"/></td>
      </tr>
    </table>
    <?php submit_button(__('حفظ الإعدادات', 'vmp')); ?>
  </form>
</div>
