/**
 * Vendor Marketplace - Product Management
 * ✅ الإصدار النهائي المُصحَّح
 * ✅ يستخدم الكائن الموحد vmp_public
 * ✅ يتحقق من الصفحة الحالية قبل التنفيذ
 * ✅ دالة showNotice موحدة
 * ✅ استخدام nonce مخصص مع fallback
 */
(function($) {
    'use strict';

    // ── التحقق من وجود vmp_public ──
    if (typeof vmp_public === 'undefined') {
        console.error('[VMP] vmp_public is not defined. Products page will not work.');
        return;
    }

    // ── التحقق من أننا في صفحة المنتجات ──
    if (vmp_public.page !== 'products') {
        return;
    }

    // ── دالة مساعدة لعرض الإشعارات ──
    function showNotice(message, type) {
        if (typeof VMP !== 'undefined' && typeof VMP.showNotice === 'function') {
            VMP.showNotice(message, type);
            return;
        }
        alert(message);
    }

    // ── تنفيذ عند تحميل الصفحة ──
    $(function() {
        // ── حذف المنتج ──
        $(document).on('click', '.vmp-delete-product', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var productId = $btn.data('product-id');
            var nonce = $btn.data('nonce') || vmp_public.nonce;

            if (!productId) {
                showNotice('معرف المنتج غير صالح.', 'error');
                return;
            }

            // رسالة تأكيد مخصصة
            var productName = $btn.closest('tr').find('td:nth-child(2) strong').text().trim();
            var confirmMessage = productName
                ? 'هل أنت متأكد من حذف المنتج "' + productName + '"؟'
                : (vmp_public.strings.confirm_delete || 'هل أنت متأكد من حذف هذا المنتج؟');

            if (!confirm(confirmMessage)) {
                return;
            }

            var originalText = $btn.text();
            $btn.prop('disabled', true).text(vmp_public.strings.loading || 'جاري...');

            $.post(vmp_public.ajax_url, {
                action: 'vmp_delete_product',
                vendor_product_id: productId,
                nonce: nonce
            })
            .done(function(response) {
                if (response.success) {
                    var $row = $btn.closest('tr');

                    // تأثير تلاشي وإزالة الصف
                    $row.fadeOut(400, function() {
                        $(this).remove();

                        // تحديث عداد المنتجات
                        var $count = $('.vmp-card-title');
                        var currentText = $count.text();
                        var match = currentText.match(/\((\d+)\)/);

                        if (match) {
                            var current = parseInt(match[1], 10);
                            if (current > 0) {
                                var newCount = current - 1;
                                $count.text(currentText.replace(/\(\d+\)/, '(' + newCount + ')'));
                            }
                        }

                        // عرض رسالة نجاح
                        showNotice(response.data.message || 'تم حذف المنتج بنجاح.', 'success');

                        // إذا لم يتبق منتجات، إعادة تحميل الصفحة
                        if ($('.vmp-table tbody tr:visible').length === 0) {
                            setTimeout(function() {
                                location.reload();
                            }, 600);
                        }
                    });
                } else {
                    var errorMsg = response.data && response.data.message
                        ? response.data.message
                        : vmp_public.strings.error;
                    showNotice(errorMsg, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function(jqXHR) {
                var errorMsg = 'حدث خطأ في الاتصال.';

                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    errorMsg = jqXHR.responseJSON.data.message;
                }

                showNotice(errorMsg, 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        });
    });

})(jQuery);