/**
 * Vendor Requests Admin JS
 */

(function($) {
    'use strict';
    
    let currentRequestId = null;
    
    $(document).ready(function() {
        initModals();
        initActions();
    });
    
    function initModals() {
        // Close on overlay click
        $(document).on('click', '.vmp-modal-overlay', function() {
            closeModal($(this).closest('.vmp-modal'));
        });
        
        // Close on button click
        $(document).on('click', '.vmp-modal-close, .vmp-modal-close-btn', function() {
            closeModal($(this).closest('.vmp-modal'));
        });
        
        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.vmp-modal:not([hidden])').each(function() {
                    closeModal($(this));
                });
            }
        });
    }
    
    function initActions() {
        // View request
        $(document).on('click', '.vmp-btn-view', function(e) {
            e.preventDefault();
            currentRequestId = $(this).data('request-id');
            openViewModal(currentRequestId);
        });
        
        // Approve request
        $(document).on('click', '.vmp-btn-approve', function(e) {
            e.preventDefault();
            const requestId = $(this).data('request-id');
            if (confirm(vmpVendorRequests.i18n.confirmApprove)) {
                doAction('approve', requestId);
            }
        });
        
        // Reject request
        $(document).on('click', '.vmp-btn-reject', function(e) {
            e.preventDefault();
            currentRequestId = $(this).data('request-id');
            $('#vmp-reject-reason').val('').focus();
            openModal('#vmp-reject-modal');
        });
        
        // Confirm reject
        $(document).on('click', '.vmp-btn-confirm-reject', function() {
            const reason = $('#vmp-reject-reason').val().trim();
            if (!reason) {
                alert(vmpVendorRequests.i18n.enterReason);
                return;
            }
            doAction('reject', currentRequestId, reason);
            closeModal('#vmp-reject-modal');
        });
        
        // Delete request
        $(document).on('click', '.vmp-btn-delete', function(e) {
            e.preventDefault();
            const requestId = $(this).data('request-id');
            if (confirm(vmpVendorRequests.i18n.confirmDelete)) {
                doAction('delete', requestId);
            }
        });
    }
    
    function openViewModal(requestId) {
        $('#vmp-modal-body').html('<div class="vmp-loading">جاري التحميل...</div>');
        openModal('#vmp-request-modal');
        
        $.post(vmpVendorRequests.ajaxUrl, {
            action: 'vmp_vendor_requests_action',
            nonce: vmpVendorRequests.nonce,
            action_type: 'view',
            request_id: requestId
        })
        .done(function(response) {
            if (response.success) {
                renderRequestDetails(response.data);
            } else {
                $('#vmp-modal-body').html('<p class="vmp-error">' + (response.data?.message || 'فشل التحميل') + '</p>');
            }
        })
        .fail(function() {
            $('#vmp-modal-body').html('<p class="vmp-error">حدث خطأ في الاتصال</p>');
        });
    }
    
    function renderRequestDetails(data) {
        let html = '<div class="vmp-request-detail">';
        
        const fields = [
            { key: 'id', label: 'معرف الطلب' },
            { key: 'store_name', label: 'اسم المتجر' },
            { key: 'store_slug', label: 'رابط المتجر' },
            { key: 'store_description', label: 'وصف المتجر' },
            { key: 'store_address', label: 'عنوان المتجر' },
            { key: 'store_phone', label: 'رقم الجوال' },
            { key: 'store_email', label: 'بريد المتجر' },
            { key: 'whatsapp_number', label: 'رقم واتساب' },
            { key: 'status', label: 'الحالة' },
            { key: 'created_at', label: 'تاريخ الطلب' },
            { key: 'admin_notes', label: 'ملاحظات المشرف' },
        ];
        
        fields.forEach(field => {
            let value = data[field.key];
            if (!value && value !== 0) return;
            
            if (field.key === 'store_slug') {
                value = '<a href="' + value + '" target="_blank" rel="noopener">' + value + '</a>';
            } else if (field.key === 'status') {
                const statusLabels = { pending: 'قيد المراجعة', approved: 'مقبولة', rejected: 'مرفوضة' };
                value = '<span class="vmp-status-badge vmp-status-' + value + '">' + (statusLabels[value] || value) + '</span>';
            } else if (field.key === 'created_at') {
                value = new Date(value.replace(' ', 'T')).toLocaleString('ar-SA');
            }
            
            const isFull = ['store_description', 'store_address', 'admin_notes'].includes(field.key);
            html += '<div class="vmp-detail-field' + (isFull ? ' vmp-detail-full' : '') + '">';
            html += '<div class="vmp-detail-label">' + field.label + '</div>';
            html += '<div class="vmp-detail-value">' + value + '</div>';
            html += '</div>';
        });
        
        // Images
        ['store_logo', 'store_banner', 'license_file'].forEach(key => {
            if (data[key] && data[key].url) {
                const labels = { store_logo: 'الشعار', store_banner: 'صورة الغلاف', license_file: 'الرخصة' };
                html += '<div class="vmp-detail-field vmp-detail-full">';
                html += '<div class="vmp-detail-label">' + labels[key] + '</div>';
                html += '<div class="vmp-detail-value"><img src="' + data[key].url + '" alt="" class="vmp-detail-image"></div>';
                html += '</div>';
            }
        });
        
        html += '</div>';
        $('#vmp-modal-body').html(html);
    }
    
    function doAction(action, requestId, reason = '') {
        const $row = $('tr[data-request-id="' + requestId + '"]');
        $row.addClass('vmp-loading');
        
        const postData = {
            action: 'vmp_vendor_requests_action',
            nonce: vmpVendorRequests.nonce,
            action_type: action,
            request_id: requestId
        };
        
        if (reason) postData.reason = reason;
        
        $.post(vmpVendorRequests.ajaxUrl, postData)
        .done(function(response) {
            $row.removeClass('vmp-loading');
            if (response.success) {
                showNotice(response.data.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 800);
            } else {
                showNotice(response.data?.message || vmpVendorRequests.i18n.actionError, 'error');
            }
        })
        .fail(function() {
            $row.removeClass('vmp-loading');
            showNotice(vmpVendorRequests.i18n.actionError, 'error');
        });
    }
    
    function openModal(selector) {
        $(selector).removeAttr('hidden');
    }
    
    function closeModal($modal) {
        $modal.attr('hidden', 'hidden');
    }
    
    function showNotice(message, type) {
        const $notice = $('<div class="notice notice-' + (type === 'error' ? 'error' : 'success') + ' is-dismissible"><p>' + message + '</p></div>');
        $('.vmp-vendor-requests-wrap').prepend($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() { $(this).remove(); });
        }, 5000);
    }
    
})(jQuery);