/**
 * Vendor Marketplace - Add Product Page
 * Handles: Featured image & Gallery selection via VMPMediaPicker
 * Dependencies: jquery, vmp-media-picker (which provides vmp_media + VMPMediaPicker)
 * 
 * This mirrors vendor-edit-product.js but for new product creation (no pre-existing images).
 */
(function ($) {
    'use strict';

    // Wait for VMPMediaPicker to be ready
    function waitForPicker(callback) {
        if (typeof window.VMPMediaPicker !== 'undefined') {
            callback();
        } else {
            setTimeout(function () {
                waitForPicker(callback);
            }, 50);
        }
    }

    waitForPicker(function () {
        var $featuredPreview = $('#vmp-featured-preview');
        var $imageIdInput = $('#image_id');
        var $selectFeaturedBtn = $('#vmp-select-featured');
        var $removeFeaturedBtn = $('#vmp-remove-featured');
        var $galleryWrap = $('#vmp-gallery-wrap');
        var $addGalleryBtn = $('#vmp-add-gallery');

        // Initially hide remove button (no featured image on new product)
        $removeFeaturedBtn.hide();

        // ── Manage Stock toggle (was inline onchange in template) ──
        $('body').on('change', '#vmp-manage-stock', function () {
            $('#vmp_stock_qty_wrap').toggle(this.value === 'yes');
        });

        // ── Featured Image Selection ──
        $selectFeaturedBtn.on('click', function () {
            window.VMPMediaPicker.open({
                mode: 'single',
                type: 'image',
                title: vmp_media.i18n?.selectFeatured || 'اختر الصورة الرئيسية',
                selectText: vmp_media.i18n?.useThis || 'استخدام هذه الصورة',
                uploadEnabled: true,
                onSelect: function (items) {
                    if (items && items.length > 0) {
                        var item = items[0];
                        $imageIdInput.val(item.attachment_id);
                        $featuredPreview.html('<img src="' + escAttr(item.url) + '" alt="' + escAttr(productAltText()) + '" loading="lazy" decoding="async">');
                        $removeFeaturedBtn.show();
                    }
                }
            });
        });

        // ── Remove Featured Image ──
        $removeFeaturedBtn.on('click', function () {
            $imageIdInput.val('0');
            $featuredPreview.empty();
            $(this).hide();
        });

        // ── Gallery Selection (Multiple) ──
        $addGalleryBtn.on('click', function () {
            // Collect existing gallery IDs to avoid duplicates
            var existingIds = [];
            $galleryWrap.find('input[name="gallery_image_ids[]"]').each(function () {
                existingIds.push(parseInt($(this).val(), 10));
            });

            window.VMPMediaPicker.open({
                mode: 'multiple',
                type: 'image',
                title: vmp_media.i18n?.selectGallery || 'اختر صور المعرض',
                selectText: vmp_media.i18n?.addToGallery || 'إضافة للمعالج',
                uploadEnabled: true,
                onSelect: function (items) {
                    if (!items || items.length === 0) {
                        return;
                    }
                    items.forEach(function (item) {
                        var aid = item.attachment_id;
                        if (existingIds.indexOf(aid) !== -1) {
                            return; // Already in gallery
                        }
                        existingIds.push(aid);
                        appendGalleryItem(item);
                    });
                }
            });
        });

        // ── Append Gallery Item to DOM ──
        function appendGalleryItem(item) {
            var html = '<div class="vmp-gallery-item">' +
                '<input type="hidden" name="gallery_image_ids[]" value="' + escAttr(item.attachment_id) + '">' +
                '<img src="' + escAttr(item.url) + '" alt="' + escAttr(productAltText()) + '" loading="lazy" decoding="async">' +
                '<button type="button" class="vmp-remove-gallery" data-attachment-id="' + escAttr(item.attachment_id) + '" aria-label="' + escAttr(vmp_media?.i18n?.removeImage || 'إزالة الصورة') + '">×</button>' +
                '</div>';
            $galleryWrap.append(html);
        }

        // ── Remove Gallery Item (Event Delegation) ──
        $galleryWrap.on('click', '.vmp-remove-gallery', function () {
            $(this).closest('.vmp-gallery-item').remove();
        });

        // Helper: dynamic alt text based on product name (falls back to generic)
        function productAltText() {
            var name = $('#vmp-product-name').val() || '';
            return name ? (vmp_media?.i18n?.productImageAlt || 'صورة المنتج: ') + name : (vmp_media?.i18n?.productImage || 'صورة المنتج');
        }

        // Helper: escape attribute for safe HTML insertion
        function escAttr(str) {
            return String(str).replace(/&/g, '&').replace(/"/g, '"').replace(/'/g, '&#039;').replace(/</g, '<').replace(/>/g, '>');
        }
    });

})(jQuery);
