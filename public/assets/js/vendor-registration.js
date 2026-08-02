/**
 * Vendor Registration Multi-Step Form - JavaScript Module
 * Handles all client-side interactions for the vendor registration form
 */

(function($) {
    'use strict';

    // Configuration from wp_localize_script
    const config = window.vmpVendorReg || {};
    const $form = $('#vmp-vendor-registration-form');
    const $steps = $('.vmp-reg-step');
    const $progressFill = $('.vmp-progress-fill');
    const $stepIndicators = $('.vmp-step-indicator');
    const $messages = $('#vmp-reg-messages');
    const $submitBtn = $('#vmp-submit-btn');
    const $summary = $('#vmp-reg-summary');
    const $summaryMessage = $('#vmp-summary-message');

    let currentStep = config.currentStep || 1;
    let stepData = {};
    let slugCheckTimeout = null;
    let isSubmitting = false;

    // Initialize
    $(document).ready(function() {
        initForm();
        bindEvents();
        loadTermsContent();
        updateProgress();
    });

    function initForm() {
        // Prefill logged-in user data
        if (config.userLoggedIn && config.currentUser) {
            if (!config.skipStep1) {
                $('#user_email').val(config.currentUser.email).prop('readonly', true);
                $('#first_name').val(config.currentUser.first_name);
                $('#last_name').val(config.currentUser.last_name);
            }
            
            // Try to generate slug from display name
            if (!$('#store_slug').val() && config.currentUser.display_name) {
                const slug = generateSlug(config.currentUser.display_name);
                $('#store_slug').val(slug);
                checkSlugAvailability(slug);
            }
        }

        // Initialize password strength meter
        initPasswordStrength();

        // Initialize media uploaders
        initMediaUploaders();

        // Load terms if URL provided
        if (config.settings?.termsUrl) {
            loadTerms(config.settings.termsUrl);
        }
    }

    function bindEvents() {
        // Navigation buttons
        $form.on('click', '.vmp-btn-next', function() {
            const nextStep = parseInt($(this).data('next-step'), 10);
            goToStep(nextStep);
        });

        $form.on('click', '.vmp-btn-prev', function() {
            const prevStep = parseInt($(this).data('prev-step'), 10);
            goToStep(prevStep);
        });

        // Step indicator clicks (only for completed steps)
        $stepIndicators.on('click', function() {
            const step = parseInt($(this).data('step'), 10);
            if (step < currentStep || $(this).hasClass('completed')) {
                goToStep(step);
            }
        });

        // Form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            submitForm();
        });

        // Real-time slug checking with debounce
        $('#store_slug').on('input', debounce(function() {
            const slug = sanitizeSlug($(this).val());
            $(this).val(slug);
            checkSlugAvailability(slug);
        }, 300));

        // Media upload buttons
        $form.on('click', '.vmp-btn-upload', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            openMediaUploader(target);
        });

        // Media remove buttons
        $form.on('click', '.vmp-btn-remove', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            removeMedia(target);
        });

        // Drag and drop for media
        $form.on('dragover dragenter', '.vmp-media-uploader', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });

        $form.on('dragleave dragend drop', '.vmp-media-uploader', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });

        $form.on('drop', '.vmp-media-uploader', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            
            const file = e.originalEvent.dataTransfer.files[0];
            if (file) {
                const target = $(this).data('field');
                uploadMediaFile(target, file);
            }
        });

        // Terms checkbox validation
        $('#terms_accepted').on('change', function() {
            toggleSubmitButton();
        });

        // Password toggle
        $form.on('click', '.vmp-toggle-password', function() {
            const $input = $(this).prev('input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $(this).find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
        });

        // Input validation on blur
        $form.on('blur', 'input[required], textarea[required], select[required]', function() {
            validateField($(this));
        });

        // Clear error on input
        $form.on('input change', 'input, textarea, select', function() {
            const $field = $(this);
            if ($field.hasClass('error')) {
                validateField($field);
            }
        });
    }

    // Step Navigation
    function goToStep(step) {
        if (step < 1 || step > 3 || step === currentStep) return;

        // Validate current step before proceeding
        if (step > currentStep && !validateCurrentStep()) {
            return;
        }

        // Save current step data
        saveStepData(currentStep);

        // Hide current step
        $(`#vmp-step-${currentStep}`).attr('hidden', 'hidden').removeClass('active');
        
        // Show new step
        const $newStep = $(`#vmp-step-${step}`);
        $newStep.removeAttr('hidden').addClass('active');
        
        currentStep = step;
        $('#vmp-current-step').val(currentStep);
        
        updateProgress();
        updateStepIndicators();
        scrollToForm();
        
        // Focus first input in new step
        $newStep.find('input:visible, textarea:visible, select:visible').first().focus();
    }

    function updateProgress() {
        const percent = (currentStep / 3) * 100;
        $progressFill.css('width', percent + '%');
    }

    function updateStepIndicators() {
        $stepIndicators.each(function() {
            const step = parseInt($(this).data('step'), 10);
            $(this).toggleClass('active', step === currentStep);
            $(this).toggleClass('completed', step < currentStep);
        });
    }

    // Validation
    function validateCurrentStep() {
        let isValid = true;
        const $currentStep = $(`#vmp-step-${currentStep}`);
        
        $currentStep.find('input[required], textarea[required], select[required]').each(function() {
            if (!validateField($(this))) {
                isValid = false;
            }
        });

        // Step 3: Terms checkbox
        if (currentStep === 3) {
            if (!$('#terms_accepted').is(':checked')) {
                showFieldError('terms_accepted', config.messages?.termsRequired || 'يجب الموافقة على الشروط والأحكام');
                isValid = false;
            }
        }

        return isValid;
    }

    function validateField($field) {
        const name = $field.attr('name');
        let isValid = true;
        let errorMessage = '';

        // Required check
        if ($field.prop('required') && !$field.val().trim()) {
            isValid = false;
            errorMessage = config.messages?.fieldRequired || 'هذا الحقل مطلوب';
        }

        // Email validation
        if (isValid && $field.attr('type') === 'email' && $field.val()) {
            if (!isValidEmail($field.val())) {
                isValid = false;
                errorMessage = config.messages?.invalidEmail || 'بريد إلكتروني غير صحيح';
            }
        }

        // Phone validation
        if (isValid && $field.attr('type') === 'tel' && $field.val()) {
            if (!isValidPhone($field.val())) {
                isValid = false;
                errorMessage = config.messages?.invalidPhone || 'رقم هاتف غير صحيح';
            }
        }

        // Slug format validation
        if (isValid && name === 'store_slug' && $field.val()) {
            const slug = $field.val();
            if (!/^[a-z0-9\-]+$/.test(slug)) {
                isValid = false;
                errorMessage = 'رابط المتجر يجب أن يحتوي على حروف إنجليزية صغيرة وأرقام وشرطات فقط';
            }
        }

        // Update UI
        $field.toggleClass('error', !isValid);
        showFieldError(name, errorMessage);

        return isValid;
    }

    function showFieldError(fieldName, message) {
        const $error = $(`.vmp-field-error[data-field="${fieldName}"]`);
        if (message) {
            $error.text(message).addClass('visible');
        } else {
            $error.text('').removeClass('visible');
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidPhone(phone) {
        return /^[\d\s\+\-\(\)]{8,}$/.test(phone);
    }

    function sanitizeSlug(slug) {
        return slug
            .toLowerCase()
            .replace(/[^a-z0-9\-]/g, '')
            .replace(/\-+/g, '-')
            .replace(/^\-|\-$/g, '');
    }

    function generateSlug(text) {
        return text
            .toLowerCase()
            .replace(/[^a-z0-9\s\-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/\-+/g, '-')
            .replace(/^\-|\-$/g, '')
            .substring(0, 60);
    }

    // Slug Availability Check
    function checkSlugAvailability(slug) {
        if (!slug || slug.length < 2) {
            updateSlugStatus('', '');
            return;
        }

        clearTimeout(slugCheckTimeout);
        const $status = $('.vmp-slug-status');
        $status.removeClass('available taken').addClass('checking').text(config.messages?.slugChecking || 'جاري التحقق...');

        slugCheckTimeout = setTimeout(() => {
            $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'vmp_check_store_slug',
                    nonce: config.nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        updateSlugStatus(data.available ? 'available' : 'taken', data.message);
                    } else {
                        updateSlugStatus('error', response.data?.message || 'فشل التحقق');
                    }
                },
                error: function() {
                    updateSlugStatus('error', 'خطأ في الاتصال');
                }
            });
        }, 200);
    }

    function updateSlugStatus(status, message) {
        const $status = $('.vmp-slug-status');
        $status.removeClass('checking available taken error').addClass(status);
        
        if (status === 'available') {
            $status.html('<span class="dashicons dashicons-yes-alt"></span> ' + (message || config.messages?.slugAvailable));
        } else if (status === 'taken') {
            $status.html('<span class="dashicons dashicons-no-alt"></span> ' + (message || config.messages?.slugTaken));
        } else {
            $status.text(message);
        }
    }

    // Media Uploader
    function initMediaUploaders() {
        // WordPress Media Uploader is initialized per click
    }

    function openMediaUploader(target) {
        const type = $(`.vmp-media-uploader[data-field="${target}"]`).data('type') || 'image';
        
        const mimeTypes = {
            logo: 'image/jpeg,image/png,image/webp,image/svg+xml',
            banner: 'image/jpeg,image/png,image/webp',
            license: 'image/jpeg,image/png,image/webp,application/pdf'
        };

        if (typeof wp === 'undefined' || !wp.media) {
            alert('مكتبة ووردبريس غير متاحة');
            return;
        }

        const frame = wp.media({
            title: target === 'store_logo' ? 'اختر شعار المتجر' : 
                   target === 'store_banner' ? 'اختر صورة الغلاف' : 'اختر ملف الرخصة',
            button: { text: 'اختيار' },
            multiple: false,
            library: { type: type === 'license' ? ['image', 'application/pdf'] : 'image' }
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            setMediaPreview(target, attachment);
        });

        frame.open();
    }

    function setMediaPreview(target, attachment) {
        const $uploader = $(`.vmp-media-uploader[data-field="${target}"]`);
        const $preview = $(`#${target}_preview`);
        const $input = $(`#${target}`);
        const $removeBtn = $uploader.find('.vmp-btn-remove');

        if (attachment.type === 'image' || attachment.subtype === 'svg') {
            $preview.html(`<img src="${attachment.url}" alt="${escapeHtml(attachment.title)}" class="vmp-media-img">`);
        } else {
            $preview.html(`
                <div class="vmp-file-icon dashicons dashicons-media-document"></div>
                <div class="vmp-file-name">${escapeHtml(attachment.filename)}</div>
            `);
        }

        $input.val(attachment.id);
        $removeBtn.show();
    }

    function removeMedia(target) {
        const $uploader = $(`.vmp-media-uploader[data-field="${target}"]`);
        const $preview = $(`#${target}_preview`);
        const $input = $(`#${target}`);
        const $removeBtn = $uploader.find('.vmp-btn-remove');

        $preview.empty();
        $input.val('');
        $removeBtn.hide();
    }

    function uploadMediaFile(target, file) {
        const $uploader = $(`.vmp-media-uploader[data-field="${target}"]`);
        const $preview = $(`#${target}_preview`);
        const $removeBtn = $uploader.find('.vmp-btn-remove');

        $preview.html('<div class="vmp-spinner" style="margin: auto;"></div>');

        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'vmp_vendor_upload_media');
        formData.append('nonce', config.nonce);
        formData.append('type', $uploader.data('type') || 'logo');

        $.ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $preview.empty();
                    if (response.data.file.type.startsWith('image/')) {
                        $preview.html(`<img src="${response.data.file.url}" alt="" class="vmp-media-img">`);
                    } else {
                        $preview.html(`
                            <div class="vmp-file-icon dashicons dashicons-media-document"></div>
                            <div class="vmp-file-name">${escapeHtml(response.data.file.url.split('/').pop())}</div>
                        `);
                    }
                    $(`#${target}`).val(response.data.file.id);
                    $removeBtn.show();
                } else {
                    $preview.html(`<p style="color: var(--vmp-error);">${response.data?.message || 'فشل الرفع'}</p>`);
                }
            },
            error: function() {
                $preview.html(`<p style="color: var(--vmp-error);">${config.messages?.uploadError || 'فشل رفع الملف'}</p>`);
            }
        });
    }

    // Password Strength
    function initPasswordStrength() {
        $('#user_pass').on('input', function() {
            const password = $(this).val();
            const $strength = $('.vmp-password-strength');
            
            if (!password) {
                $strength.removeClass('weak medium strong').find('.vmp-strength-bar').css('width', '0%');
                return;
            }

            let score = 0;
            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            $strength.removeClass('weak medium strong');
            if (score <= 2) {
                $strength.addClass('weak');
            } else if (score <= 3) {
                $strength.addClass('medium');
            } else {
                $strength.addClass('strong');
            }
        });
    }

    // Terms Content
    function loadTermsContent() {
        if (config.settings?.termsUrl) {
            loadTerms(config.settings.termsUrl);
        }
    }

    function loadTerms(url) {
        const $content = $('#vmp-terms-content');
        $content.html('<p style="text-align:center; color: var(--vmp-text-muted);">جاري تحميل الشروط...</p>');

        // Try to load via iframe for same-origin, or fetch for cross-origin
        try {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.width = '100%';
            iframe.style.height = '400px';
            iframe.style.border = '1px solid var(--vmp-border)';
            iframe.style.borderRadius = 'var(--vmp-radius-sm)';
            iframe.onload = function() {
                try {
                    // Try to access content if same origin
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    $content.empty().append(doc.body.innerHTML);
                } catch (e) {
                    // Cross-origin, show iframe as is
                }
            };
            $content.empty().append(iframe);
        } catch (e) {
            $content.html('<p style="color: var(--vmp-error);">تعذر تحميل الشروط والأحكام</p>');
        }
    }

    // Form Submission
    function saveStepData(step) {
        const $step = $(`#vmp-step-${step}`);
        $step.find('input, textarea, select').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            if (name) {
                if ($field.attr('type') === 'checkbox') {
                    stepData[name] = $field.is(':checked');
                } else if ($field.attr('type') === 'radio') {
                    if ($field.is(':checked')) {
                        stepData[name] = $field.val();
                    }
                } else {
                    stepData[name] = $field.val();
                }
            }
        });
    }

    function submitForm() {
        if (isSubmitting) return;
        
        // Validate final step
        if (!validateCurrentStep()) {
            return;
        }

        // Save all step data
        saveStepData(1);
        saveStepData(2);
        saveStepData(3);

        isSubmitting = true;
        $submitBtn.addClass('loading').prop('disabled', true);
        showMessage(config.messages?.submitting || 'جاري الإرسال...', 'info');

        // Prepare form data
        const formData = new FormData($form[0]);
        formData.append('action', 'vmp_vendor_registration_submit');

        // Use REST API if available, otherwise AJAX
        if (config.restUrl) {
            fetch(`${config.restUrl}vendor-register/submit`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': config.nonce
                },
                body: formData
            })
            .then(response => response.json())
            .then(handleSubmitResponse)
            .catch(handleSubmitError);
        } else {
            $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: handleSubmitResponse,
                error: handleSubmitError
            });
        }
    }

    function handleSubmitResponse(response) {
        isSubmitting = false;
        $submitBtn.removeClass('loading').prop('disabled', false);

        if (response.success) {
            showMessage(response.data.message || config.messages?.submitSuccess, 'success');
            
            // Hide form, show summary
            $form.hide();
            $summaryMessage.html(response.data.message);
            $summary.removeAttr('hidden');
            
            // Update user meta if needed
            if (response.data.redirect_to) {
                $summary.find('a').attr('href', response.data.redirect_to);
            }
        } else {
            const message = response.data?.message || config.messages?.submitError;
            showMessage(message, 'error');
            
            // Show field errors if provided
            if (response.data?.errors) {
                Object.keys(response.data.errors).forEach(field => {
                    showFieldError(field, response.data.errors[field]);
                    $(`[name="${field}"]`).addClass('error');
                });
            }
        }
    }

    function handleSubmitError() {
        isSubmitting = false;
        $submitBtn.removeClass('loading').prop('disabled', false);
        showMessage(config.messages?.submitError || 'حدث خطأ في الاتصال بالخادم', 'error');
    }

    // Utility Functions
    function showMessage(message, type) {
        const iconMap = {
            success: 'dashicons-yes-alt',
            error: 'dashicons-no-alt',
            warning: 'dashicons-warning',
            info: 'dashicons-info'
        };

        const $msg = $(`
            <div class="vmp-message vmp-message-${type}">
                <span class="dashicons ${iconMap[type]}"></span>
                <div>${message}</div>
            </div>
        `);

        $messages.empty().append($msg);

        // Auto-hide success/info messages
        if (type === 'success' || type === 'info') {
            setTimeout(() => {
                $msg.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
        }
    }

    function toggleSubmitButton() {
        const checked = $('#terms_accepted').is(':checked');
        $submitBtn.prop('disabled', !checked);
    }

    function scrollToForm() {
        $('html, body').animate({
            scrollTop: $form.offset().top - 50
        }, 300);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Expose public methods for testing/extension
    window.VMPVendorRegistration = {
        goToStep,
        validateCurrentStep,
        submitForm,
        showMessage
    };

})(jQuery);