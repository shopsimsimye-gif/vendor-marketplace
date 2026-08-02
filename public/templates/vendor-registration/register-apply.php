<?php
/* Template: Apply as Vendor (for logged-in users) */
// Posts to /wp-json/vmp/v1/vendor/apply
?>
<div class="vmp-register-apply">
  <h2><?php _e('طلب ترقية إلى بائع', 'vmp'); ?></h2>
  <form id="vmp-register-apply" method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('vmp_register_apply', 'vmp_register_apply_nonce'); ?>
    <label><?php _e('الاسم الأول', 'vmp'); ?></label>
    <input name="first_name" />

    <label><?php _e('الاسم الأخير', 'vmp'); ?></label>
    <input name="last_name" />

    <label><?php _e('اسم المستخدم', 'vmp'); ?></label>
    <input name="username" disabled />

    <label><?php _e('رقم الموبايل', 'vmp'); ?></label>
    <input name="phone" />

    <label><?php _e('الدولة', 'vmp'); ?></label>
    <input name="country" />

    <label><?php _e('وثيقة أو ترخيص النشاط التجاري (اختياري)', 'vmp'); ?></label>
    <input name="license_document" type="file" accept="application/pdf,image/*" />

    <label>
      <input name="accept_terms" type="checkbox" value="1" required /> <?php _e('أوافق على الشروط والأحكام', 'vmp'); ?>
    </label>

    <?php $recaptcha_key = get_option('vmp_recaptcha_site_key'); if (!empty($recaptcha_key)): ?>
      <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($recaptcha_key); ?>"></div>
      <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>

    <button type="submit"><?php _e('إرسال الطلب', 'vmp'); ?></button>
  </form>
</div>

<script>
(function(){
  const form = document.getElementById('vmp-register-apply');
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const data = new FormData(form);
    fetch('<?php echo esc_url(rest_url('vmp/v1/vendor/apply')); ?>', {
      method: 'POST',
      credentials: 'same-origin',
      body: data
    }).then(res => res.json()).then(json => {
      if (json.success) {
        alert(json.message || 'تم إرسال الطلب');
        window.location.reload();
      } else {
        alert(json.error || 'حدث خطأ');
      }
    }).catch(()=>alert('خطأ في الشبكة'));
  });
})();
</script>
