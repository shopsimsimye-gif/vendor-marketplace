/**
 * Vendor Marketplace — Public JS (الإصدار النهائي)
 * يعالج AJAX، النماذج متعددة الخطوات، رفع الصور، الرسوم البيانية، وتتبع واتساب
 */
(function ($) {
    'use strict';

    // حماية من فقدان vmp_public أو strings
    if (typeof vmp_public === 'undefined') {
        window.vmp_public = {};
    }
    if (typeof vmp_public.strings === 'undefined') {
        vmp_public.strings = {
            loading: 'جاري الإرسال...',
            error: 'حدث خطأ، يرجى المحاولة مرة أخرى.',
            confirm_delete: 'هل أنت متأكد من الحذف؟',
            next: 'التالي',
            prev: 'السابق',
            submit: 'إرسال الطلب'
        };
    }

    const VMP = {
        init: function () {
            try {
                this.bindEvents();
                this.initCharts();
                // this.initMultiStepForm(); // معطل — يتعارض مع vendor-register.js
                this.initMediaUploader();
                this.trackWhatsAppClicks();
            } catch (error) {
                console.error('[VMP] init failed:', error);
            }
        },

        bindEvents: function () {
            const self = this;

            $(document).on('submit', '.vmp-ajax-form', function (e) { self.handleAjaxForm.call(this, e); });
            $(document).on('click', '#vmp-global-notice', function () { $(this).removeClass('show'); });
            $(document).on('click', '.vmp-btn-upgrade-plan', function (e) { self.handlePlanUpgrade.call(this, e); });
            $(document).on('click', '.vmp-btn-cancel-plan',  function (e) { self.handlePlanCancel.call(this, e); });
            $(document).on('input', '#vmp_product_price, #vmp_product_sale_price', function () { self.calculateEarnings(); });
        },

        showNotice: function (message, type = 'success') {
            let $notice = $('#vmp-global-notice');
            if (!$notice.length) {
                $('body').append('<div id="vmp-global-notice"></div>');
                $notice = $('#vmp-global-notice');
            }
            $notice.removeClass('success error warning info').addClass(type)
                   .text(message).addClass('show');
            setTimeout(() => { $notice.removeClass('show'); }, 4000);
        },

        handleAjaxForm: function (e) {
            e.preventDefault();
            const $form   = $(this);
            const $btn    = $form.find('button[type="submit"]');
            const $loader = $('.vmp-loading');
            const action  = $form.data('action');

            if (!action) return;

            const originalBtnText = $btn.html();
            $btn.prop('disabled', true).text(vmp_public.strings.loading);
            $loader.addClass('show');

            const formData = new FormData(this);
            formData.append('action', action);

            // ✅ معالجة الـ nonce حسب نوع الطلب
            if (action === 'vmp_vendor_register') {
                // استخدام الـ nonce الخاص بالتسجيل
                const registerNonce = vmp_public.register_nonce || vmp_public.nonce;
                formData.delete('nonce');
                formData.append('nonce', registerNonce);
                console.log('[VMP] Register Nonce used:', registerNonce);
            } else {
                formData.delete('nonce');
                formData.append('nonce', vmp_public.nonce);
            }

            $.ajax({
                url: vmp_public.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    $loader.removeClass('show');
                    $btn.prop('disabled', false).html(originalBtnText);

                    if (res.success) {
                        VMP.showNotice(res.data.message || 'Success', 'success');
                        if (res.data.redirect) {
                            window.location.href = res.data.redirect;
                        } else if ($form.hasClass('vmp-reset-on-success')) {
                            $form[0].reset();
                            $form.find('.vmp-image-preview').removeClass('show').attr('src', '').hide();
                            $form.find('input[name="image_id"]').val('');
                            $form.find('.upload-icon, .vmp-image-upload p').show();

                            if (action === 'vmp_vendor_register') {
                                const $steps = $form.find('.vmp-step-content');
                                const $navItems = $form.closest('.vmp-card').find('.vmp-step');
                                const $lines = $form.closest('.vmp-card').find('.vmp-step-line');
                                $steps.removeClass('active').first().addClass('active');
                                $navItems.removeClass('active done').first().addClass('active');
                                $lines.removeClass('done');
                            }
                        }
                    } else {
                        VMP.showNotice(res.data.message || vmp_public.strings.error, 'error');
                    }
                },
                error: function (xhr) {
                    $loader.removeClass('show');
                    $btn.prop('disabled', false).html(originalBtnText);
                    console.error('[VMP] AJAX Error:', xhr.responseText);
                    
                    let errorMessage = vmp_public.strings.error;
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        if (xhr.responseJSON.data.errors && Array.isArray(xhr.responseJSON.data.errors) && xhr.responseJSON.data.errors.length > 0) {
                            errorMessage = xhr.responseJSON.data.errors.join('<br>');
                        } else if (xhr.responseJSON.data.message) {
                            errorMessage = xhr.responseJSON.data.message;
                        }
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    VMP.showNotice(errorMessage, 'error');
                }
            });
        },

        initMultiStepForm: function () {
            $(document).on('click', '#vmp-register-form .vmp-btn-next', function (e) {
                e.preventDefault();
                const $form     = $(this).closest('form');
                const $steps    = $form.find('.vmp-step-content');
                const $navItems = $form.closest('.vmp-card').find('.vmp-step');
                const $lines    = $form.closest('.vmp-card').find('.vmp-step-line');
                const currentStep = $steps.index($steps.filter('.active'));

                let isValid = true;
                $steps.eq(currentStep).find('input[required], select[required], textarea[required]').each(function () {
                    const value = ($(this).val() || '').toString().trim();
                    if (!value) {
                        isValid = false;
                        $(this).addClass('vmp-input-error');
                        $(this).on('input change', function () { $(this).removeClass('vmp-input-error'); });
                    }
                });

                if (!isValid) { VMP.showNotice('يرجى تعبئة جميع الحقول المطلوبة', 'error'); return; }

                const nextStep = currentStep + 1;
                if (nextStep >= $steps.length) return;

                $steps.eq(currentStep).removeClass('active');
                $navItems.eq(currentStep).removeClass('active').addClass('done');
                if (currentStep < $lines.length) $lines.eq(currentStep).addClass('done');
                $steps.eq(nextStep).addClass('active');
                $navItems.eq(nextStep).addClass('active');
                $('html, body').animate({ scrollTop: $form.offset().top - 80 }, 300);
            });

            $(document).on('click', '#vmp-register-form .vmp-btn-prev', function (e) {
                e.preventDefault();
                const $form     = $(this).closest('form');
                const $steps    = $form.find('.vmp-step-content');
                const $navItems = $form.closest('.vmp-card').find('.vmp-step');
                const $lines    = $form.closest('.vmp-card').find('.vmp-step-line');
                const currentStep = $steps.index($steps.filter('.active'));
                const prevStep    = currentStep - 1;
                if (prevStep < 0) return;

                $steps.eq(currentStep).removeClass('active');
                $navItems.eq(currentStep).removeClass('active');
                if (currentStep <= $lines.length) $lines.eq(currentStep - 1).removeClass('done');
                $navItems.eq(currentStep).removeClass('done');
                $steps.eq(prevStep).addClass('active');
                $navItems.eq(prevStep).removeClass('done').addClass('active');
                $('html, body').animate({ scrollTop: $form.offset().top - 80 }, 300);
            });
        },

        initMediaUploader: function () {
            // صفحة الملف الشخصي لها معالجها الخاص (vendor-profile.js) —
            // تجنب فتح نافذتين من مكتبة الوسائط عند النقر.
            if (document.getElementById('vmp-profile-form')) {
                return;
            }
            $(document).on('click', '.vmp-image-upload', function (e) {
                e.preventDefault();
                const $container = $(this);
                const $input = $container.find('input[type="hidden"]');
                const $img = $container.find('.vmp-image-preview');
                const $text = $container.find('p, .upload-icon');

                if (typeof wp === 'undefined' || !wp.media) {
                    VMP.showNotice('مكتبة الوسائط غير محملة', 'error');
                    return;
                }

                let mediaUploader = $container.data('mediaUploader');
                if (!mediaUploader) {
                    mediaUploader = wp.media({
                        title: 'اختر صورة المنتج',
                        button: { text: 'استخدام الصورة' },
                        multiple: false,
                        library: { type: 'image' }
                    });
                    mediaUploader.on('select', function () {
                        const attachment = mediaUploader.state().get('selection').first().toJSON();
                        $input.val(attachment.id);
                        $img.attr('src', attachment.url).show().addClass('show');
                        $text.hide();
                    });
                    $container.data('mediaUploader', mediaUploader);
                }
                mediaUploader.open();
            });
        },

        initCharts: function () {
            const ctx = document.getElementById('vmp-vendor-chart');
            if (!ctx || typeof Chart === 'undefined') return;
            if (typeof vmp_public === 'undefined' || !vmp_public.ajax_url) return;

            $.post(vmp_public.ajax_url, {
                action: 'vmp_vendor_chart',
                nonce: vmp_public.nonce,
                months: 6
            }, function (res) {
                if (res.success && res.data) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: res.data.labels,
                            datasets: [{
                                label: 'الأرباح (رس)',
                                data: res.data.earnings,
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99,102,241,0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4
                            }, {
                                label: 'الطلبات',
                                data: res.data.orders,
                                borderColor: '#10b981',
                                backgroundColor: 'transparent',
                                borderWidth: 3,
                                tension: 0.4,
                                yAxisID: 'y1'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                y: { type: 'linear', display: true, position: 'right' },
                                y1: { type: 'linear', display: true, position: 'left', grid: { drawOnChartArea: false } }
                            },
                            plugins: {
                                legend: { labels: { font: { family: 'Cairo' } } },
                                tooltip: { titleFont: { family: 'Cairo' }, bodyFont: { family: 'Cairo' } }
                            }
                        }
                    });
                }
            });
        },

        handlePlanUpgrade: function (e) {
            e.preventDefault();
            const planId = $(this).data('plan-id');
            const planName = $(this).closest('.vmp-plan-card').find('.vmp-plan-name').text();
            if (!confirm(`هل أنت متأكد من الاشتراك في الخطة: ${planName}؟`)) return;

            $('.vmp-loading').addClass('show');
            $.post(vmp_public.ajax_url, {
                action: 'vmp_request_plan_change',
                nonce: vmp_public.nonce,
                plan_id: planId
            }, function (res) {
                $('.vmp-loading').removeClass('show');
                if (res.success) { VMP.showNotice(res.data.message || res.message || 'تم إرسال الطلب', 'success'); }
                else { VMP.showNotice(res.data.message || vmp_public.strings.error, 'error'); }
            });
        },

        handlePlanCancel: function (e) {
            e.preventDefault();
            if (!confirm(vmp_public.strings.confirm_delete || 'هل أنت متأكد من إلغاء الاشتراك؟')) return;
            $('.vmp-loading').addClass('show');
            $.post(vmp_public.ajax_url, {
                action: 'vmp_cancel_subscription',
                nonce: vmp_public.nonce
            }, function (res) {
                $('.vmp-loading').removeClass('show');
                if (res.success) { VMP.showNotice(res.data.message, 'success'); setTimeout(() => location.reload(), 1500); }
                else { VMP.showNotice(res.data.message, 'error'); }
            });
        },

        trackWhatsAppClicks: function () {
            $(document).on('click', '.vmp-whatsapp-btn, .vmp-wa-track', function () {
                console.log('[VMP] WhatsApp button clicked');
                const $btn = $(this);
                const vendorId = $btn.data('vendor-id');
                const productId = $btn.data('product-id');
                const clickType = $btn.data('click-type');
                const pageUrl = window.location.href;

                $.ajax({
                    url: vmp_public.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vmp_track_whatsapp_click',
                        nonce: vmp_public.nonce,
                        vendor_id: vendorId || 0,
                        product_id: productId || 0,
                        click_type: clickType || 'button',
                        page_url: pageUrl
                    },
                    async: true,
                    timeout: 3000
                }).done(function(res) {
                    console.log('[VMP] WhatsApp tracking success:', res);
                }).fail(function(xhr) {
                    console.error('[VMP] WhatsApp tracking failed:', xhr.responseText);
                });
            });
        },

        calculateEarnings: function () {
            const priceField = $('#vmp_product_price');
            const saleField = $('#vmp_product_sale_price');
            const earningsField = $('#vmp_calc_earnings');
            const commissionField = $('#vmp_calc_commission');

            if (!priceField.length || !saleField.length || !earningsField.length || !commissionField.length) {
                return;
            }

            const price = parseFloat(priceField.val()) || 0;
            const salePrice = parseFloat(saleField.val()) || 0;
            const finalPrice = salePrice > 0 ? salePrice : price;
            const rate = window.vmp_commission_rate || 10;
            const commission = (finalPrice * rate) / 100;
            const earnings = finalPrice - commission;
            earningsField.text(earnings.toFixed(2));
            commissionField.text(commission.toFixed(2));
        }
    };

    $(document).ready(function () { VMP.init(); });

})(jQuery);
