jQuery(document).ready(function($) {
    // ── تحديد إشعار كمقروء ──
    $(document).on('click', '.vmp-notice-mark-read', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var noticeId = $btn.data('notice-id');

        $.post(vmp_public.ajax_url, {
            action: 'vmp_mark_notice_read',
            nonce: vmp_public.nonce,
            notice_id: noticeId
        }, function(response) {
            if (response.success) {
                $btn.closest('.vmp-notice-item').addClass('vmp-notice-read');
                $btn.remove();
            }
        });
    });

    // ── تحديد الكل كمقروء ──
    $('#vmp-mark-all-read').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);

        $.post(vmp_public.ajax_url, {
            action: 'vmp_mark_all_notices_read',
            nonce: vmp_public.nonce
        }, function(response) {
            if (response.success) {
                $('.vmp-notice-item').addClass('vmp-notice-read');
                $('.vmp-notice-mark-read').remove();
            }
        });
    });
});
