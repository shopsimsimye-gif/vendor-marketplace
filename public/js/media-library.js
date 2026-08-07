/**
 * VMP Vendor Media Library
 * Depends: jQuery
 * Globals: vmp_media (localized via MediaModule enqueueAssets - single source object)
 *
 * [QA 2026-08-07] Unified upload path (user-approved architecture, refined per PR review).
 * PHASE 1 (safe): "Select from WordPress" removed from the UI.
 *   - Upload New is the ONLY path to add new files.
 *   - openMediaFrame() / selectAttachment() removed.
 *   - Reuse of existing media happens through the vmp_media grid rendered below.
 *
 * PHASE 2 (after reference verification): MediaController::select() + vmp_media_select
 *   registration become dead code and get removed from the Controller and RouteRegistry.
 */
(function($) {
    'use strict';

    if (typeof vmp_media === 'undefined' || !vmp_media.ajax_url || !vmp_media.nonce) {
        if (window.console && console.warn) {
            console.warn('VMP Media Library: vmp_media data missing. Library disabled.');
        }
        return;
    }

    const VMPMedia = {
        page: 1,
        perPage: 20,
        loading: false,
        hasMore: true,
        $confirmEls: null,

        init() {
            this.cacheDOM();
            this.bindEvents();
            this.loadMedia();
        },

        cacheDOM() {
            this.$grid = $('#vmp-media-grid');
            this.$uploadBtn = $('#vmp-media-upload');
            this.$loadMore = $('#vmp-media-load-more');
            this.$count = $('#vmp-media-count');
            this.$fileInput = $('#vmp-media-file-input');
            this.$wrap = $('#vmp-media-library');
        },

        bindEvents() {
            this.$uploadBtn.on('click', (e) => {
                e.preventDefault();
                this.$fileInput.trigger('click');
            });

            this.$fileInput.on('change', (e) => {
                const files = e.target.files;
                if (files && files.length) {
                    this.uploadFile(files[0]);
                }
                e.target.value = '';
            });

            this.$grid.on('click', '.vmp-media-delete', (e) => {
                e.stopPropagation();
                const $item = $(e.currentTarget).closest('.vmp-media-item');
                this.askDelete($item.data('id'));
            });

            this.$grid.on('click', '.vmp-media-item', (e) => {
                if ($(e.target).closest('.vmp-media-delete').length) return;
                const $item = $(e.currentTarget).closest('.vmp-media-item');
                this.selectMedia($item.data('id'), $item.find('img').attr('src'));
            });

            this.$loadMore.on('click', (e) => {
                e.preventDefault();
                this.loadMore();
            });
        },

        uploadFile(file) {
            if (!file || this.loading) return;

            this.setLoading(true);
            this.$uploadBtn.prop('disabled', true);

            const formData = new FormData();
            formData.append('action', 'vmp_media_upload');
            formData.append('nonce', vmp_media.nonce);
            formData.append('file', file);

            $.ajax({
                url: vmp_media.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done((response) => {
                if (response && response.success) {
                    this.showToast((response.data && response.data.message) || this.t('uploadSuccess'), 'success');
                    this.loadMedia();
                } else {
                    this.showToast((response && response.data && response.data.message) || this.t('uploadError'), 'error');
                }
            }).fail((xhr) => {
                let msg = this.t('uploadError');
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                this.showToast(msg, 'error');
            }).always(() => {
                this.setLoading(false);
                this.$uploadBtn.prop('disabled', false);
            });
        },

        loadMedia() {
            this.page = 1;
            this.$grid.empty();
            this.fetchMedia();
        },

        loadMore() {
            if (this.loading || !this.hasMore) return;
            this.page++;
            this.fetchMedia();
        },

        fetchMedia() {
            this.setLoading(true);

            $.get(vmp_media.ajax_url, {
                action: 'vmp_media_list',
                page: this.page,
                per_page: this.perPage,
                nonce: vmp_media.nonce
            }, (response) => {
                this.setLoading(false);
                if (response && response.success) {
                    this.renderItems(response.data.data, this.page > 1);
                    this.hasMore = response.data.page < response.data.total_pages;
                    this.$loadMore.toggle(this.hasMore);
                    this.setCount(response.data.total);
                }
            }).fail(() => {
                this.setLoading(false);
                this.showToast(this.t('networkError'), 'error');
            });
        },

        renderItems(items, append) {
            if (!items || !items.length) {
                if (this.page === 1) {
                    this.$grid.html('<p class="vmp-media-empty">' + this.t('noMedia') + '</p>');
                }
                return;
            }
            if (append) {
                items.forEach((item) => this.$grid.append(this.itemTemplate(item)));
            } else {
                this.$grid.html(items.map((item) => this.itemTemplate(item)).join(''));
            }
        },

        itemTemplate(item) {
            const thumb = item.thumbnail || item.url || '';
            const typeClass = item.type || 'image';
            return '<div class="vmp-media-item" data-id="' + (item.id || '') + '" data-attachment="' + ((item.attachment_id || item.attachmentId) || '') + '">' +
                '<div class="vmp-media-thumb">' +
                    '<img src="' + thumb + '" alt="" loading="lazy">' +
                    '<span class="vmp-media-type">' + typeClass + '</span>' +
                '</div>' +
                '<div class="vmp-media-meta">' +
                    '<span class="vmp-media-size">' + this.formatBytes(item.file_size) + '</span>' +
                    '<button type="button" class="vmp-media-delete" title="' + this.t('delete') + '">' +
                        '<span class="dashicons dashicons-trash"></span>' +
                    '</button>' +
                '</div>' +
            '</div>';
        },

        askDelete(id) {
            this.setConfirm(this.t('confirmDelete'), () => this.deleteMedia(id));
        },

        deleteMedia(id) {
            if (this.loading) return;
            this.setLoading(true);

            $.post(vmp_media.ajax_url, {
                action: 'vmp_media_delete',
                media_id: id,
                nonce: vmp_media.nonce
            }, (response) => {
                this.setLoading(false);
                if (response && response.success) {
                    this.showToast(this.t('deleted'), 'success');
                    this.loadMedia();
                } else {
                    this.showToast((response && response.data && response.data.message) || this.t('uploadError'), 'error');
                }
            }).fail(() => {
                this.setLoading(false);
                this.showToast(this.t('networkError'), 'error');
            });
        },

        selectMedia(id, url) {
            $(document).trigger('vmp:media:selected', { id, url });
        },

        setLoading(state) {
            this.loading = state;
            this.$grid.toggleClass('vmp-media-loading', state);
        },

        setCount(count) {
            const n = parseInt(count, 10) || 0;
            this.$count.text(n);
        },

        formatBytes(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.min(Math.floor(Math.log(bytes) / Math.log(k)), sizes.length - 1);
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        showToast(message, type) {
            if (!message) return;
            this.ensureToastContainer();
            const $toast = $('<div class="vmp-toast vmp-toast-' + (type || 'info') + '"></div>').text(message);
            $('#vmp-media-toasts').append($toast);
            requestAnimationFrame(() => $toast.addClass('vmp-toast-visible'));
            setTimeout(() => {
                $toast.addClass('vmp-toast-hiding');
                setTimeout(() => $toast.remove(), 300);
            }, 3200);
        },

        ensureToastContainer() {
            if ($('#vmp-media-toasts').length) return;
            this.$wrap.append('<div id="vmp-media-toasts"></div>');
        },

        setConfirm(message, onConfirm) {
            this.closeConfirm();
            const $overlay = $('<div class="vmp-modal-overlay"></div>');
            const $modal = $('<div class="vmp-confirm-modal" role="dialog" aria-modal="true"></div>');
            $modal.append('<p class="vmp-confirm-message"></p>');
            $modal.find('.vmp-confirm-message').text(message);
            $modal.append('<div class="vmp-confirm-actions"></div>');
            $modal.find('.vmp-confirm-actions')
                .append('<button type="button" class="button vmp-confirm-cancel"></button>')
                .append('<button type="button" class="button button-primary vmp-confirm-ok"></button>');
            $modal.find('.vmp-confirm-cancel').text(this.t('cancel')).on('click', () => this.closeConfirm());
            $modal.find('.vmp-confirm-ok').text(this.t('confirm')).on('click', () => {
                this.closeConfirm();
                if (typeof onConfirm === 'function') onConfirm();
            });

            $overlay.append($modal);
            this.$wrap.append($overlay);
            this.$confirmEls = { overlay: $overlay, modal: $modal };
        },

        closeConfirm() {
            if (this.$confirmEls) {
                this.$confirmEls.overlay.remove();
                this.$confirmEls = null;
            }
        },

        t(key) {
            const fallback = {
                selectOrUpload: 'Select or Upload Media',
                useThisMedia: 'Use this media',
                confirmDelete: 'Are you sure you want to delete this file?',
                delete: 'Delete',
                noMedia: 'No media files found.',
                uploadError: 'Upload failed. Please try again.',
                uploadSuccess: 'Uploaded successfully.',
                networkError: 'Network error. Please try again.',
                deleted: 'Deleted successfully.',
                selected: 'Selected.',
                cancel: 'Cancel',
                confirm: 'Confirm',
                mediaUnavailable: 'Media library is not available.'
            };
            return (vmp_media.i18n && vmp_media.i18n[key]) || fallback[key];
        }
    };

    $(document).ready(() => {
        if ($('#vmp-media-library').length) {
            VMPMedia.init();
        }
    });

    window.VMPMedia = VMPMedia;

})(jQuery);
