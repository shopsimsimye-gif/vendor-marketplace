<?php
/* Template: Wizard Step 1 - Account */
$user = wp_get_current_user();
$draft = null;
if (is_user_logged_in()) {
    $resp = wp_remote_get(rest_url('vmp/v1/vendor/draft'));
    if (!is_wp_error($resp)) {
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $draft = $body['draft'] ?? null;
    }
}
?>
<div class="vmp-wizard-step">
  <h2><?php _e('حساب البائع', 'vmp'); ?></h2>
  <form id="vmp-wizard-step-1">
    <label><?php _e('الاسم الأول', 'vmp'); ?></label>
    <input name="first_name" value="<?php echo esc_attr($draft['first_name'] ?? $user->first_name); ?>" />
    <label><?php _e('اسم المستخدم', 'vmp'); ?></label>
    <input name="username" value="<?php echo esc_attr($draft['username'] ?? $user->user_login); ?>" />
    <label><?php _e('البريد الإلكتروني', 'vmp'); ?></label>
    <input name="email" value="<?php echo esc_attr($draft['email'] ?? $user->user_email); ?>" />
    <!-- password UI for guests -->
    <button type="button" id="vmp-save-continue"><?php _e('حفظ ومتابعة', 'vmp'); ?></button>
  </form>
</div>
