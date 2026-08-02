<?php
/* Template: Register Guest (public) */
// This template displays a registration form for guests to apply as vendors.
// It posts to the REST endpoint /wp-json/vmp/v1/vendor/register-guest
?>
<div class="vmp-register-guest">
  <h2><?php _e('التسجيل كبائع', 'vmp'); ?></h2>
  <form id="vmp-register-guest" method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('vmp_register_guest', 'vmp_register_guest_nonce'); ?>
    <label><?php _e('الاسم الأول', 'vmp'); ?></label>
    <input name="first_name" required />

    <label><?php _e('الاسم الأخير', 'vmp'); ?></label>
    <input name="last_name" required />

    <label><?php _e('اسم المستخدم', 'vmp'); ?></label>
    <input name="username" required />

    <label><?php _e('رقم الموبايل', 'vmp'); ?></label>
    <input name="phone" required />

    <label><?php _e('الدولة', 'vmp'); ?></label>
    <input name="country" required />

    <label><?php _e('البريد الإلكتروني', 'vmp'); ?></label>
    <input name="email" type="email" required />

    <label><?php _e('كلمة المرور', 'vmp'); ?></label>
    <input name="password" type="password" required />

    <label><?php _e('وثيقة أو ترخيص النشاط التجاري (اختياري)', 'vmp'); ?></label>
    <input name="license_document" type="file" accept="application/pdf,image/*" />

    <label>
      <input name="accept_terms" type="checkbox" value="1" required /> <?php _e('أوافق على الشروط والأحكام', 'vmp'); ?>
    </label>

    <?php $recaptcha_key = get_option('vmp_recaptcha_site_key'); if (!empty($recaptcha_key)): ?>
      <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($recaptcha_key); ?>"></div>
      <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>

    <button type="submit"><?php _e('تسجيل', 'vmp'); ?></button>
  </form>
</div>

<script>
(function(){
  const form = document.getElementById('vmp-register-guest');
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const data = new FormData(form);
    fetch('<?php echo esc_url(rest_url('vmp/v1/vendor/register-guest')); ?>', {
      method: 'POST',
      credentials: 'same-origin',
      body: data
    }).then(res => res.json()).then(json => {
      if (json.success) {
        alert(json.message || 'تم إرسال الطلب');
        window.location.href = '<?php echo esc_url(site_url('/')); ?>';
      } else {
        alert(json.error || 'حدث خطأ');
      }
    }).catch(()=>alert('خطأ في الشبكة'));
  });
})();
</script>
