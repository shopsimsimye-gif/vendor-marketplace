/* vendor-subscriptions.js - loaded only on subscriptions page. vmp_subs_data localized. */
jQuery(document).ready(function($) {
    'use strict';
    var T = window.vmp_subs_data ? window.vmp_subs_data.i18n : {};

    // ── طلب تغيير الخطة ──
    $(document).on('click', '.vmp-btn-request-plan-change', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var planId = $btn.data('plan-id');
        var planName = $btn.data('plan-name');

        if (!confirm((T.confirmChange || 'Are you sure you want to change your plan to') + ' "' + planName + '"? ' + (T.willReview || 'The request will be reviewed by admin.'))) {
            return;
        }

        $btn.prop('disabled', true).text(T.sending || 'Sending...');
        $('.vmp-loading').addClass('show');

        $.post(vmp_public.ajax_url, {
            action: 'vmp_request_plan_change',
            nonce: vmp_public.nonce,
            plan_id: planId
        }, function(response) {
            $('.vmp-loading').removeClass('show');
            $btn.prop('disabled', false).text(T.requestChange || 'Request plan change');

            if (response.success) {
                VMP.showNotice(response.data.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                VMP.showNotice(response.data.message, 'error');
            }
        }).fail(function() {
            $('.vmp-loading').removeClass('show');
            $btn.prop('disabled', false).text(T.requestChange || 'Request plan change');
            VMP.showNotice(T.connError || 'Connection error.', 'error');
        });
    });

    // ── إلغاء طلب تغيير الخطة ──
    $(document).on('click', '.vmp-cancel-plan-change', function(e) {
        e.preventDefault();

        if (!confirm(T.confirmCancel || 'Are you sure you want to cancel the plan change request?')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(T.canceling || 'Processing...');
        $('.vmp-loading').addClass('show');

        $.post(vmp_public.ajax_url, {
            action: 'vmp_cancel_plan_change',
            nonce: vmp_public.nonce
        }, function(response) {
            $('.vmp-loading').removeClass('show');
            $btn.prop('disabled', false).text(T.cancelRequest || 'Cancel request');

            if (response.success) {
                VMP.showNotice(response.data.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                VMP.showNotice(response.data.message, 'error');
            }
        }).fail(function() {
            $('.vmp-loading').removeClass('show');
            $btn.prop('disabled', false).text(T.cancelRequest || 'Cancel request');
            VMP.showNotice(T.connError || 'Connection error.', 'error');
        });
    });
});
