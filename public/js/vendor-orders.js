/* vendor-orders.js - loaded only on orders page. vmp_orders_data localized. */
jQuery(document).ready(function($) {
    var T = window.vmp_orders_data ? window.vmp_orders_data.i18n : {};

    // فتح الـ Modal عند النقر على زر التفاصيل
    $(document).on('click', '.vmp-order-details-btn', function(e) {
        e.preventDefault();
        var vendorOrderId = $(this).data('vendor-order-id');
        var orderNumber = $(this).data('order-number');
        var $modal = $('#vmp-order-modal');
        $('#vmp-order-modal-id').text('#' + orderNumber);
        $('#vmp-order-modal-body').html('<p>' + (T.loading || 'Loading...') + '</p>');
        $modal.prop('hidden', false);

        $.post(vmp_public.ajax_url, {
            action: 'vmp_get_order_details',
            nonce: vmp_public.nonce,
            vendor_order_id: vendorOrderId
        }, function(response) {
            if (response.success && response.data) {
                var d = response.data;
                var html = '';
                var v = d.vendor_order || {};
                html += '<p><strong>' + (T.orderNumber || 'Order #') + '</strong> ' + (d.order_number || v.order_id || '') + '</p>';
                if (d.customer_name) {
                    html += '<p><strong>' + (T.customer || 'Customer') + '</strong> ' + d.customer_name + '</p>';
                }
                if (d.customer_email) {
                    html += '<p><strong>' + (T.email || 'Email') + '</strong> ' + d.customer_email + '</p>';
                }
                if (d.order_date) {
                    html += '<p><strong>' + (T.date || 'Date') + '</strong> ' + d.order_date + '</p>';
                }
                if (v.total) {
                    html += '<p class="vmp-order-modal-total"><strong>' + (T.total || 'Total') + ' ' + v.total + '</strong></p>';
                }
                $('#vmp-order-modal-body').html(html || '<p>' + (T.noDetails || 'No details') + '</p>');
            } else {
                $('#vmp-order-modal-body').html('<p>' + (response.data && response.data.message ? response.data.message : (T.loadFailed || 'Could not load')) + '</p>');
            }
        }).fail(function() {
            $('#vmp-order-modal-body').html('<p>' + (T.loadError || 'Error loading') + '</p>');
        });
    });

    // إغلاق الـ Modal
    $(document).on('click', '[data-close-modal]', function() {
        $('#vmp-order-modal').prop('hidden', true);
    });
});
