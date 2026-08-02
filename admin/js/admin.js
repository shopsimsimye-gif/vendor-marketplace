/**
 * Vendor Marketplace — Admin JS
 * Handles AJAX actions in WP Admin, charts, modals, and bulk operations.
 * ✅ النسخة النهائية المتوافقة مع جميع التحديثات السابقة
 */
(function ($) {
    'use strict';

    const VMP_Admin = {
        init: function () {
            this.bindEvents();
            this.initCharts();
            this.initTabs();
            this.initPlanFeatures(); // ✅ دعم Toggles في خطة الاشتراك
        },

        bindEvents: function () {
            // General Form Submission (Settings, Plans, etc.)
            $(document).on('submit', '.vmp-admin-ajax-form', this.handleAjaxForm);

            // Action Buttons (Approve, Reject, Delete, Pay)
            $(document).on('click', '.vmp-action-btn', this.handleActionClick);

            // Modals
            $(document).on('click', '[data-modal-target]', this.openModal);
            $(document).on('click', '.vmp-modal-close, .vmp-modal-cancel', this.closeModal);
            $(document).on('click', '.vmp-modal-overlay', function (e) {
                if (e.target === this) VMP_Admin.closeModal();
            });

            // Bulk Actions
            $(document).on('click', '#vmp-bulk-pay-btn', this.handleBulkPay);
            $(document).on('change', '#cb-select-all', this.toggleCheckboxes);
        },

        // ─────────────────────────────────────────────────────────────────
        // General AJAX Handler
        // ─────────────────────────────────────────────────────────────────
        handleAjaxForm: function (e) {
            if (this.id === 'vmp-settings-form') {
                return;
            }

            e.preventDefault();
            const $form = $(this);
            const action = $form.data('action');
            if (!action) return;

            const $btn = $form.find('button[type="submit"]');
            const originalBtnText = $btn.text();

            $btn.prop('disabled', true).text(vmp_admin.strings.loading || 'جاري التحميل...');
            $('.vmp-admin-loading').addClass('show');

            const formData = new FormData(this);
            formData.append('action', action);
            formData.append('nonce', vmp_admin.nonce);

            $.ajax({
                url: vmp_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    $('.vmp-admin-loading').removeClass('show');
                    $btn.prop('disabled', false).text(originalBtnText);

                    if (res.success) {
                        alert(res.data.message || 'تمت العملية بنجاح');
                        if ($form.data('reload')) {
                            location.reload();
                        } else {
                            VMP_Admin.closeModal();
                        }
                    } else {
                        alert(res.data.message || vmp_admin.strings.error);
                    }
                },
                error: function () {
                    $('.vmp-admin-loading').removeClass('show');
                    $btn.prop('disabled', false).text(originalBtnText);
                    alert(vmp_admin.strings.error);
                }
            });
        },

        // ─────────────────────────────────────────────────────────────────
        // Action Buttons
        // ─────────────────────────────────────────────────────────────────
        handleActionClick: function (e) {
            e.preventDefault();
            const $btn = $(this);
            const action = $btn.data('action');
            const id = $btn.data('id');
            const confirmMsg = $btn.data('confirm');

            if (!action || !id) return;
            if (confirmMsg && !confirm(confirmMsg)) return;

            $('.vmp-admin-loading').addClass('show');

            const payload = {
                action: action,
                nonce: vmp_admin.nonce,
            };

            if (action === 'vmp_admin_approve_product' || action === 'vmp_admin_reject_product') {
                payload.vendor_product_id = id;
            } else {
                payload.id = id;
            }

            $.post(vmp_admin.ajax_url, payload, function (res) {
                $('.vmp-admin-loading').removeClass('show');
                if (res.success) {
                    alert(res.data.message);
                    location.reload();
                } else {
                    alert(res.data.message || vmp_admin.strings.error);
                }
            }).fail(function () {
                $('.vmp-admin-loading').removeClass('show');
                alert(vmp_admin.strings.error);
            });
        },

        // ─────────────────────────────────────────────────────────────────
        // Bulk Operations
        // ─────────────────────────────────────────────────────────────────
        toggleCheckboxes: function () {
            const isChecked = $(this).prop('checked');
            $('.vmp-row-cb').prop('checked', isChecked);
        },

        handleBulkPay: function (e) {
            e.preventDefault();
            const ids = [];
            $('.vmp-row-cb:checked').each(function () {
                ids.push($(this).val());
            });

            if (ids.length === 0) {
                alert('لم يتم تحديد أي عناصر');
                return;
            }

            if (!confirm('هل أنت متأكد من الدفع للعناصر المحددة؟')) return;

            $('.vmp-admin-loading').addClass('show');

            $.post(vmp_admin.ajax_url, {
                action: 'vmp_bulk_pay_commissions',
                nonce: vmp_admin.nonce,
                ids: ids
            }, function (res) {
                $('.vmp-admin-loading').removeClass('show');
                if (res.success) {
                    alert(res.data.message);
                    location.reload();
                } else {
                    alert(res.data.message || vmp_admin.strings.error);
                }
            });
        },

        // ─────────────────────────────────────────────────────────────────
        // Modals (مع دعم Toggles للمميزات)
        // ─────────────────────────────────────────────────────────────────
        openModal: function (e) {
            e.preventDefault();
            const targetId = $(this).data('modal-target');
            const $modal = $('#' + targetId);

            // ── إذا كان المودال خاص بخطة الاشتراك ──
            if (targetId === 'vmp-plan-modal') {
                const plan = $(this).data('plan');

                // إعادة تعيين النموذج
                $modal.find('form')[0]?.reset();
                $modal.find('form').data('action', 'vmp_admin_create_plan');
                $modal.find('[name="plan_id"]').val('');
                $modal.find('.vmp-modal-header h2').text('إضافة خطة جديدة');

                // إلغاء تحديد جميع الـ toggles
                $modal.find('.vmp-feature-input').prop('checked', false);

                if (plan) {
                    // ✅ تعديل خطة موجودة
                    $modal.find('form').data('action', 'vmp_admin_update_plan');
                    $modal.find('[name="plan_id"]').val(plan.id);
                    $modal.find('[name="name"]').val(plan.name);
                    $modal.find('[name="description"]').val(plan.description || '');
                    $modal.find('[name="price"]').val(plan.price);
                    $modal.find('[name="billing_period"]').val(plan.billing_period);
                    $modal.find('[name="commission_rate"]').val(plan.commission_rate);
                    $modal.find('[name="max_products"]').val(plan.max_products);
                    $modal.find('[name="is_active"]').val(plan.is_active);
                    $modal.find('.vmp-modal-header h2').text('تعديل الخطة');

                    // ✅ تعبئة المميزات (Features Toggles)
                    if (plan.features) {
                        try {
                            const features = typeof plan.features === 'string'
                                ? JSON.parse(plan.features)
                                : plan.features;

                            // إلغاء تحديد الكل أولاً
                            $modal.find('.vmp-feature-input').prop('checked', false);

                            // تعيين التحديد حسب الميزات النشطة
                            $.each(features, function (key, value) {
                                if (value === true || value === 1 || value === '1') {
                                    const $input = $modal.find('input[name="features[' + key + ']"]');
                                    if ($input.length) {
                                        $input.prop('checked', true);
                                    }
                                }
                            });
                        } catch (e) {
                            console.warn('فشل تحليل الميزات:', e);
                        }
                    }
                }

                $modal.addClass('show');
                return;
            }

            // ── مودالات أخرى ──
            $modal.addClass('show');
        },

        closeModal: function () {
            $('.vmp-modal-overlay').removeClass('show');
        },

        // ─────────────────────────────────────────────────────────────────
        // Tabs
        // ─────────────────────────────────────────────────────────────────
        initTabs: function () {
            $('.vmp-admin-tab').on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');

                $('.vmp-admin-tab').removeClass('active');
                $(this).addClass('active');

                $('.vmp-tab-content').hide();
                $(target).show();
            });

            // Activate first tab by default
            if ($('.vmp-admin-tab').length > 0) {
                $('.vmp-admin-tab').first().click();
            }
        },

        // ─────────────────────────────────────────────────────────────────
        // Charts
        // ─────────────────────────────────────────────────────────────────
        initCharts: function () {
            const ctx = document.getElementById('vmp-admin-chart');
            if (!ctx || typeof Chart === 'undefined') return;

            $.post(vmp_admin.ajax_url, {
                action: 'vmp_admin_chart',
                nonce: vmp_admin.nonce,
                months: 6
            }, function (res) {
                if (res.success && res.data) {
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: res.data.labels,
                            datasets: [{
                                label: 'إجمالي المبيعات',
                                data: res.data.sales,
                                backgroundColor: 'rgba(34, 113, 177, 0.7)',
                                borderRadius: 4
                            }, {
                                label: 'أرباح البائعين',
                                data: res.data.vendor_earnings,
                                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                borderRadius: 4
                            }, {
                                label: 'عمولات الموقع',
                                data: res.data.commissions,
                                backgroundColor: 'rgba(245, 158, 11, 0.7)',
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top' }
                            }
                        }
                    });
                }
            });
        },

        // ─────────────────────────────────────────────────────────────────
        // ✅ دعم Toggles في نموذج خطة الاشتراك
        // ─────────────────────────────────────────────────────────────────
        initPlanFeatures: function () {
            // لا حاجة لإضافة منطق هنا لأن الـ toggles يتم التحكم بها عبر
            // الأحداث المباشرة في HTML (onchange في `subscriptions.php`).
            // لكن نقوم بإضافة دعم لتعيين قيم الـ toggles عند فتح المودال.
            // هذا يتم التعامل معه في `openModal`.
        }
    };

    $(document).ready(function () {
        VMP_Admin.init();
    });

})(jQuery);
