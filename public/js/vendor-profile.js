/**
 * vendor-profile.js - loaded only on profile page. vmp_profile_data localized (php-generated).
 * 
 * [QA 2026-08-08] Fixed: Removed unnecessary jQuery dependency check that blocked initialization.
 * Fixed: Added polling for VMPMediaPicker if not immediately available.
 * Fixed: Added console debugging for troubleshooting.
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // ── بيانات مشتركة: تأتي من wp_localize_script (vmp_profile_data) بدل PHP inline ──
    var VMP_DATA = window.vmp_profile_data || {};
    VMP_DATA.i18n = VMP_DATA.i18n || {};

    // ── نظام Toast Notifications ──
    function showToast(message, type) {
        type = type || 'success';
        var container = document.getElementById('vmp-toast-container');
        if (!container) return;

        var toast = document.createElement('div');
        toast.className = 'vmp-toast ' + type;

        var iconMap = {
            success: '\u2705',
            error: '\u274c',
            warning: '\u26a0\ufe0f',
            info: '\u2139\ufe0f'
        };

        toast.innerHTML = '<span>' + (iconMap[type] || '\u2713') + '</span><span>' + message + '</span>';
        container.appendChild(toast);

        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(function() {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, 3500);
    }

    // ── نسخ الرابط ──
    var copyBtn = document.querySelector('.vmp-copy-url-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var url = this.dataset.url;
            if (!url) return;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function() {
                    showToast(VMP_DATA.i18n.copied, 'success');
                }).catch(function() {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        });
    }

    function fallbackCopy(text) {
        var input = document.createElement('input');
        input.value = text;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        try {
            document.execCommand('copy');
            showToast(VMP_DATA.i18n.copied, 'success');
        } catch (err) {
            showToast(VMP_DATA.i18n.connectionError, 'error');
        }
        document.body.removeChild(input);
    }

    // ── التحقق من تفرد الـ Slug ──
    var slugInput = document.querySelector('input[name="store_slug"]');
    var slugStatus = document.getElementById('vmp-slug-status');

    if (slugInput && slugStatus) {
        var timeoutId;
        var slugRegex = /^[a-z0-9\-]+$/;

        slugInput.addEventListener('input', function() {
            var slug = this.value.trim();
            if (!slug) {
                slugStatus.innerHTML = '';
                return;
            }

            if (!slugRegex.test(slug)) {
                slugStatus.innerHTML = '\u26a0\ufe0f ' + VMP_DATA.i18n.slugInvalid;
                slugStatus.style.color = '#f59e0b';
                return;
            }

            clearTimeout(timeoutId);
            slugStatus.innerHTML = '\u23f3 ' + (VMP_DATA.i18n.checking || 'Checking...');
            slugStatus.style.color = '#64748b';

            timeoutId = setTimeout(function() {
                var formData = new URLSearchParams();
                formData.append('action', 'vmp_check_store_slug');
                formData.append('nonce', VMP_DATA.nonce);
                formData.append('slug', slug);
                formData.append('exclude_user_id', VMP_DATA.userId);

                fetch(VMP_DATA.ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success && data.data && data.data.available) {
                        slugStatus.innerHTML = '\u2705 ' + VMP_DATA.i18n.slugAvailable;
                        slugStatus.style.color = '#10b981';
                    } else {
                        slugStatus.innerHTML = '\u274c ' + VMP_DATA.i18n.slugTaken;
                        slugStatus.style.color = '#ef4444';
                    }
                })
                .catch(function() {
                    slugStatus.innerHTML = '\u26a0\ufe0f ' + VMP_DATA.i18n.slugCheckError;
                    slugStatus.style.color = '#f59e0b';
                });
            }, 500);
        });
    }

    // ── رفع الصور (VMP Media Picker — Logo / Banner) ──
    // [QA 2026-08-07] Migrated from wp.media to VMPMediaPicker (single source of truth)
    // [QA 2026-08-08] Fixed: Removed jQuery dependency check; added polling for VMPMediaPicker.
    
    function initImageUploads() {
        var containers = document.querySelectorAll('.vmp-image-upload');
        
        if (!containers.length) {
            console.warn('[VMP Profile] No .vmp-image-upload containers found');
            return;
        }

        console.log('[VMP Profile] Found ' + containers.length + ' image upload containers');

        containers.forEach(function(container) {
            var input = container.querySelector('input[type="hidden"]');
            var preview = container.querySelector('.vmp-image-preview');
            var icon = container.querySelector('.upload-icon');
            var text = container.querySelector('p');
            var removeBtn = container.querySelector('.vmp-remove-image');

            if (!input) {
                console.warn('[VMP Profile] Missing hidden input in .vmp-image-upload');
                return;
            }

            container.addEventListener('click', function(e) {
                // Don't open picker if clicking remove button
                if (e.target.closest('.vmp-remove-image')) {
                    return;
                }

                e.preventDefault();

                if (typeof window.VMPMediaPicker === 'undefined') {
                    console.error('[VMP Profile] VMPMediaPicker not loaded. Check that vmp-media-picker script is enqueued.');
                    showToast('Media picker not available', 'error');
                    return;
                }

                console.log('[VMP Profile] Opening VMPMediaPicker for type:', container.dataset.type);

                window.VMPMediaPicker.open({
                    mode: 'single',
                    type: 'image',
                    uploadEnabled: true,
                    onSelect: function(items) {
                        if (!items || !items.length) return;
                        var attachmentId = items[0].attachment_id || items[0].id;
                        var url = items[0].url || '';

                        input.value = attachmentId;
                        if (preview) {
                            preview.src = url;
                            preview.classList.add('show');
                        }
                        if (icon) icon.style.display = 'none';
                        if (text) text.style.display = 'none';
                        if (removeBtn) removeBtn.style.display = 'flex';
                        
                        console.log('[VMP Profile] Selected image:', attachmentId, url);
                    }
                });
            });

            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    input.value = '0';
                    if (preview) {
                        preview.src = '';
                        preview.classList.remove('show');
                    }
                    if (icon) icon.style.display = 'block';
                    if (text) text.style.display = 'block';
                    removeBtn.style.display = 'none';
                });
            }
        });
    }

    // Initialize immediately if VMPMediaPicker is ready, otherwise poll
    if (typeof window.VMPMediaPicker !== 'undefined' && window.VMPMediaPicker) {
        console.log('[VMP Profile] VMPMediaPicker ready, initializing...');
        initImageUploads();
    } else {
        console.log('[VMP Profile] VMPMediaPicker not ready, polling...');
        var checkInterval = setInterval(function() {
            if (typeof window.VMPMediaPicker !== 'undefined' && window.VMPMediaPicker) {
                clearInterval(checkInterval);
                console.log('[VMP Profile] VMPMediaPicker loaded, initializing...');
                initImageUploads();
            }
        }, 100);
        // Timeout after 5 seconds
        setTimeout(function() {
            clearInterval(checkInterval);
            if (typeof window.VMPMediaPicker === 'undefined') {
                console.error('[VMP Profile] VMPMediaPicker failed to load within 5 seconds. Check script dependencies.');
            }
        }, 5000);
    }

    // ── إرسال النموذج عبر AJAX ──
    var form = document.getElementById('vmp-profile-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var submitBtn = this.querySelector('button[type="submit"]');
            var originalBtnHtml = submitBtn.innerHTML;

            var password = this.querySelector('input[name="password"]');
            var confirmPassword = this.querySelector('input[name="confirm_password"]');

            if (password && confirmPassword && password.value) {
                if (password.value !== confirmPassword.value) {
                    showToast(VMP_DATA.i18n.passwordMismatch, 'error');
                    confirmPassword.focus();
                    return;
                }
                if (password.value.length < 8) {
                    showToast(VMP_DATA.i18n.passwordTooShort, 'warning');
                    password.focus();
                    return;
                }
            }

            var slugInputCheck = this.querySelector('input[name="store_slug"]');
            if (slugInputCheck) {
                var slug = slugInputCheck.value.trim();
                var slugRegex = /^[a-z0-9\-]+$/;
                if (!slugRegex.test(slug)) {
                    showToast(VMP_DATA.i18n.slugInvalid, 'warning');
                    slugInputCheck.focus();
                    return;
                }
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = VMP_DATA.i18n.saving;

            var formData = new FormData(this);
            formData.append('action', this.dataset.action || 'vmp_vendor_update_profile');

            fetch(VMP_DATA.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                var payload = data.data || {};
                if (data.success) {
                    showToast(payload.message || data.message || VMP_DATA.i18n.saved, 'success');
                    if (payload.store_slug) {
                        var urlInput = document.getElementById('vmp-store-url-input');
                        if (urlInput) {
                            urlInput.value = VMP_DATA.storeBaseUrl + payload.store_slug + '/';
                        }
                    }
                    var copyBtnEl = document.querySelector('.vmp-copy-url-btn');
                    if (copyBtnEl && payload.store_slug) {
                        copyBtnEl.dataset.url = VMP_DATA.storeBaseUrl + payload.store_slug + '/';
                    }
                } else {
                    showToast(payload.message || data.message || VMP_DATA.i18n.saveError, 'error');
                }
            })
            .catch(function() {
                showToast(VMP_DATA.i18n.connectionError, 'error');
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            });
        });
    }
});
