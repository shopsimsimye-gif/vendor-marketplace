/**
 * Vendor Marketplace — Public JS (Single-Step Vendor Registration)
 * تسجيل البائع في خطوة واحدة (تسجيل جديد أو ترقية)
 */
(function ($) {
    'use strict';

    const VMPRegister = {
        isSubmitting: false,

        init: function () {
            this.$form = $('#vmp-single-register-form');
            if (!this.$form.length) {
                return;
            }

            this.$submitBtn = $('#vmp_submit_btn');
            this.$successMsg = $('#vmp-success-message');
            this.$errorMsg = $('#vmp-error-message');
            this.$retryBtn = $('#vmp_retry_btn');

            this.bindEvents();
            this.initPasswordToggle();
        },

        bindEvents: function () {
            const self = this;

            this.$form.on('submit', function (e) {
                e.preventDefault();
                self.submitForm();
            });

            this.$retryBtn.on('click', function () {
                self.$errorMsg.hide();
                self.$form.show();
            });

            // إزالة التنبيه بالخطأ فور الإدخال
            this.$form.on('input change', 'input[required], select[required], textarea[required]', function () {
                $(this).removeClass('vmp-input-error');
                self.clearFieldError($(this).attr('name'));
            });
        },

        initPasswordToggle: function () {
            $(document).on('click', '.vmp-toggle-password', function () {
                const $btn = $(this);
                const $input = $btn.siblings('input[type="password"], input[type="text"]');
                const $icon = $btn.find('.dashicons');

                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                } else {
                    $input.attr('type', 'password');
                    $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                }
            });
        },

        validateForm: function () {
            let isValid = true;
            let firstErrorField = null;
            const self = this;

            this.$form.find('input[required], select[required], textarea[required]').each(function () {
                const $field = $(this);
                const value = $field.val() ? $field.val().toString().trim() : '';
                const type = $field.attr('type');
                const name = $field.attr('name');

                if (type === 'checkbox') {
                    if (!$field.is(':checked')) {
                        isValid = false;
                        self.showFieldError(name, window.vmpRegisterData.strings.termsRequired || 'يجب الموافقة على الشروط والأحكام');
                        if (!firstErrorField) firstErrorField = $field;
                    } else {
                        self.clearFieldError(name);
                    }
                } else if (!value) {
                    isValid = false;
                    $field.addClass('vmp-input-error');
                    if (!firstErrorField) firstErrorField = $field;
                } else {
                    $field.removeClass('vmp-input-error');
                }

                if (name === 'email' && value && !self.isValidEmail(value)) {
                    isValid = false;
                    self.showFieldError(name, 'البريد الإلكتروني غير صحيح');
                    if (!firstErrorField) firstErrorField = $field;
                }

                if (name === 'password' && value && value.length < 8) {
                    isValid = false;
                    self.showFieldError(name, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل');
                    if (!firstErrorField) firstErrorField = $field;
                }
            });

            if (!isValid && firstErrorField) {
                firstErrorField.focus();
            }

            return isValid;
        },

        isValidEmail: function (email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        showFieldError: function (fieldName, message) {
            const $field = this.$form.find('[name="' + fieldName + '"]').first();
            $field.addClass('vmp-input-error');
            const $wrapper = $field.closest('.vmp-form-group, .vmp-terms-group');
            $wrapper.find('.vmp-field-error').remove();
            $wrapper.append('<span class="vmp-field-error" style="color:#ef4444; font-size:12px; margin-top:4px; display:block;">' + message + '</span>');
        },

        clearFieldError: function (fieldName) {
            const $field = this.$form.find('[name="' + fieldName + '"]').first();
            $field.removeClass('vmp-input-error');
            const $wrapper = $field.closest('.vmp-form-group, .vmp-terms-group');
            $wrapper.find('.vmp-field-error').remove();
        },

        submitForm: function () {
            if (this.isSubmitting) return;
            if (!this.validateForm()) return;

            this.isSubmitting = true;
            const self = this;

            const $btnText = this.$submitBtn.find('.vmp-btn-text');
            const $btnLoading = this.$submitBtn.find('.vmp-btn-loading');
            $btnText.hide();
            $btnLoading.show();
            this.$submitBtn.prop('disabled', true);

            const formData = new FormData(this.$form[0]);
            
            // تحديد الـ URL بناءً على حالة تسجيل الدخول
            const targetUrl = window.vmpRegisterData.isLoggedIn 
                ? window.vmpRegisterData.restApplyUrl 
                : window.vmpRegisterData.restGuestUrl;

            $.ajax({
                url: targetUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                credentials: 'same-origin',
                success: function (res) {
                    if (res.success) {
                        self.showSuccess(res.message);
                    } else {
                        self.showError(res.error || res.message || window.vmpRegisterData.strings.error);
                    }
                },
                error: function (xhr) {
                    let msg = window.vmpRegisterData.strings.error;
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.error) {
                            msg = xhr.responseJSON.error;
                        } else if (xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                    }
                    self.showError(msg);
                },
                complete: function () {
                    self.isSubmitting = false;
                    $btnText.show();
                    $btnLoading.hide();
                    self.$submitBtn.prop('disabled', false);
                }
            });
        },

        showSuccess: function (message) {
            this.$form.hide();
            $('.vmp-header-bar').hide();
            
            if (message) {
                $('#vmp_success_text').text(message);
            }

            this.$successMsg.show();
            $('html, body').animate({ scrollTop: this.$successMsg.offset().top - 80 }, 300);
        },

        showError: function (message) {
            this.$form.hide();
            $('#vmp_error_text').html(message);
            this.$errorMsg.show();
            $('html, body').animate({ scrollTop: this.$errorMsg.offset().top - 80 }, 300);
        }
    };

    $(function () {
        VMPRegister.init();
    });

    window.VMPRegister = VMPRegister;

})(jQuery);
