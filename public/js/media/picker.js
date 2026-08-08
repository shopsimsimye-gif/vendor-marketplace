/**
 * VMP Media Picker — reusable vendor media selection component.
 * ------------------------------------------------------------
 * Depends: jQuery, vmp_media (localized object with ajax_url + nonce)
 * Globals: window.VMPMediaPicker
 *
 * [QA 2026-08-07] Architecture decision (user-approved, no wp.media override):
 *   - Do NOT touch _wpPluploadSettings globally.
 *   - Do NOT create a vmp_media_plupload shim to satisfy wp.media.
 *   - vmp_media is the single source of truth (list / upload / delete).
 *   - This component replaces wp.media inside vendor UI (Featured, Gallery,
 *     Logo, Banner, AI) progressively.
 *
 * [QA 2026-08-08] Corrections applied:
 *   - itemsMap: stores full item data to avoid DOM lookup on confirmSelect.
 *   - i18n fallback: added complete fallback map (was returning empty string).
 *   - Custom confirm modal: replaced window.confirm with unified modal.
 *   - ID normalization: vmp_media.id is primary; attachment_id is secondary.
 *
 * Usage:
 *   VMPMediaPicker.open({
 *       mode: 'single' | 'multiple',
 *       type: 'image' | 'video' | '' ,
 *       title: 'Choose an image',
 *       selectButton: 'Use this image',
 *       uploadEnabled: true,
 *       onSelect(items) -> array of { id, attachment_id, url, thumbnail, ... }
 *   });
 */
(function ($, window) {
    'use strict';

    if (typeof vmp_media === 'undefined' || !vmp_media.ajax_url || !vmp_media.nonce) {
        if (window.console && console.warn) {
            console.warn('VMP Media Picker: vmp_media data missing. Picker disabled.');
        }
        return;
    }

    var Picker = {
        $overlay: null,
        $modal: null,
        $grid: null,
        state: {
            page: 1,
            perPage: 20,
            loading: false,
            hasMore: true,
            total: 0,
            mode: 'single',
            type: '',
            selection: [],
            itemsMap: {}
        },
        config: null,
        confirmEls: null,

        open: function (cfg) {
            this.config = $.extend({
                mode: 'single',
                type: '',
                title: 'VMP Media',
                selectText: 'Use this media',
                uploadEnabled: true,
                onSelect: function () {}
            }, cfg || {});

            if (this.config.mode !== 'multiple') {
                this.config.mode = 'single';
            }

            this.state.mode = this.config.mode;
            this.state.type = this.config.type || '';
            this.state.page = 1;
            this.state.total = 0;
            this.state.hasMore = true;
            this.state.selection = [];
            this.state.itemsMap = {};

            this.build();
            this.load();
        },

        build: function () {
            var self = this;
            this.close();
            var overlay = $('<div class="vmp-picker-overlay"></div>');
            var modal = $('<div class="vmp-picker-modal" role="dialog" aria-modal="true"></div>');
            var header = $('<div class="vmp-picker-header"></div>');
            header.append('<h3 class="vmp-picker-title"></h3>');
            header.append('<button type="button" class="vmp-picker-close" aria-label="Close">&times;</button>');
            header.find('.vmp-picker-title').text(this.t('mediaLibrary') || this.config.title);

            var toolbar = $('<div class="vmp-picker-toolbar"></div>');
            var uploadBtn = $('<button type="button" class="vmp-picker-upload button button-primary"></button>')
                .text(this.t('uploadNew') || 'Upload New');
            var fileInput = $('<input type="file" class="vmp-picker-file" accept="' + this.acceptAttr() + '" style="display:none">');
            fileInput.prop('multiple', this.config.mode === 'multiple');
            var searchInput = $('<input type="search" class="vmp-picker-search" placeholder="' + (this.t('search') || 'Search') + '">');
            toolbar.append(uploadBtn).append(fileInput).append(searchInput);

            var grid = $('<div class="vmp-picker-grid"></div>');
            var footer = $('<div class="vmp-picker-footer"></div>');
            var count = $('<span class="vmp-picker-count"></span>');
            var loadMore = $('<button type="button" class="vmp-picker-loadmore button" style="display:none"></button>')
                .text(this.t('loadMore') || 'Load more');
            var doneBtn = $('<button type="button" class="vmp-picker-done button button-primary" style="display:none"></button>')
                .text(this.t('done') || 'Done');
            footer.append(count).append(loadMore).append(doneBtn);

            modal.append(header).append(toolbar).append(grid).append(footer);
            overlay.append(modal);
            $('body').append(overlay);
            this.$overlay = overlay;
            this.$modal = modal;
            this.$grid = grid;
            this.$count = count;
            this.$loadMore = loadMore;
            this.$done = doneBtn;
            this.$file = fileInput;
            this.$uploadBtn = uploadBtn;
            this.$search = searchInput;

            if (this.config.uploadEnabled === false) {
                uploadBtn.hide();
            }

            // Events
            header.find('.vmp-picker-close').on('click', function (e) {
                e.preventDefault();
                self.cancel();
            });
            overlay.on('mousedown', function (e) {
                if (e.target === overlay[0]) { self.cancel(); }
            });
            uploadBtn.on('click', function (e) {
                e.preventDefault();
                fileInput.trigger('click');
            });
            fileInput.on('change', function (e) {
                var files = e.target.files;
                self.uploadFiles(files);
                e.target.value = '';
            });
            searchInput.on('input', function () {
                self.filter();
            });
            grid.on('click', '.vmp-picker-item', function (e) {
                if ($(e.target).closest('.vmp-picker-delete').length) { return; }
                var $item = $(this);
                self.toggleSelect($item.data('id'));
            });
            grid.on('click', '.vmp-picker-delete', function (e) {
                e.stopPropagation();
                var $item = $(this).closest('.vmp-picker-item');
                self.askDelete($item.data('id'));
            });
            loadMore.on('click', function (e) {
                e.preventDefault();
                self.loadMore();
            });
            doneBtn.on('click', function (e) {
                e.preventDefault();
                self.confirmSelect();
            });
        },

        acceptAttr: function () {
            var type = this.state.type;
            if (!type) { return ''; }
            if (type === 'image') {
                return 'image/jpeg,image/png,image/gif,image/webp,image/avif';
            }
            if (type === 'video') {
                return 'video/mp4,video/webm';
            }
            return '';
        },

        load: function () {
            var self = this;
            this.state.loading = true;
            this.$grid.addClass('vmp-picker-loading');
            var params = {
                action: 'vmp_media_list',
                page: this.state.page,
                per_page: this.state.perPage,
                nonce: vmp_media.nonce
            };
            if (this.state.type) { params.type = this.state.type; }

            $.get(vmp_media.ajax_url, params, function (resp) {
                self.state.loading = false;
                self.$grid.removeClass('vmp-picker-loading');
                if (!resp || !resp.success) {
                    self.showToast((resp && resp.data && resp.data.message) || self.t('loadError') || 'Error', 'error');
                    return;
                }
                var data = resp.data.data || [];
                var append = self.state.page > 1;
                self.renderItems(data, append);
                self.state.hasMore = resp.data.page < resp.data.total_pages;
                self.state.total = resp.data.total || data.length;
                self.$loadMore.toggle(self.state.hasMore);
                self.updateCount();
            }).fail(function () {
                self.state.loading = false;
                self.$grid.removeClass('vmp-picker-loading');
                self.showToast(self.t('networkError') || 'Network error', 'error');
            });
        },

        renderItems: function (items, append) {
            var self = this;
            if (!items || !items.length) {
                if (this.state.page === 1) {
                    this.$grid.html('<p class="vmp-picker-empty">' + (this.t('noMedia') || 'No media found.') + '</p>');
                }
                return;
            }
            var html = '';
            items.forEach(function (item) {
                // Store full item data in itemsMap for confirmSelect
                var id = item.id != null ? item.id : (item.attachment_id || '');
                self.state.itemsMap[id] = item;
                html += self.itemTemplate(item);
            });
            if (append) { this.$grid.append(html); }
            else { this.$grid.html(html); }

            // Re-apply current selection highlight
            this.$grid.find('.vmp-picker-item').each(function () {
                var id = $(this).data('id');
                if (self.isSelected(id)) { $(this).addClass('selected'); }
            });
        },

        isSelected: function (id) {
            return this.state.selection.indexOf(parseInt(id, 10)) >= 0;
        },

        itemTemplate: function (item) {
            var thumb = item.thumbnail || item.url || '';
            var type = item.type || 'image';
            var id = item.id != null ? item.id : (item.attachment_id || '');
            return '<div class="vmp-picker-item" data-id="' + id + '" data-attachment="' + ((item.attachment_id || item.attachmentId) || '') + '" data-type="' + type + '">' +
                '<div class="vmp-picker-thumb"><img src="' + thumb + '" loading="lazy" alt="">' +
                '<span class="vmp-picker-type">' + type + '</span></div>' +
                '<button type="button" class="vmp-picker-delete" title="' + (this.t('delete') || 'Delete') + '">&times;</button>' +
                '</div>';
        },

        toggleSelect: function (id) {
            id = parseInt(id, 10) || 0;
            var $item = this.$grid.find('.vmp-picker-item[data-id="' + id + '"]');
            if (this.state.mode === 'single') {
                this.state.selection = [id];
                this.$grid.find('.vmp-picker-item.selected').removeClass('selected');
                $item.addClass('selected');
                this.$done.show();
            } else {
                var idx = this.state.selection.indexOf(id);
                if (idx >= 0) {
                    this.state.selection.splice(idx, 1);
                    $item.removeClass('selected');
                } else {
                    this.state.selection.push(id);
                    $item.addClass('selected');
                }
                this.$done.toggle(this.state.selection.length > 0);
            }
            this.updateSelectionSummary();
        },

        updateSelectionSummary: function () {
            if (this.state.mode === 'multiple' && this.$count.length) {
                this.$count.text(this.state.selection.length + ' / ' + this.state.total);
            }
        },

        confirmSelect: function () {
            var self = this;
            if (!this.state.selection.length) { return; }
            var result = [];
            this.state.selection.forEach(function (id) {
                var item = self.state.itemsMap[id];
                if (item) {
                    result.push({
                        id: id,
                        attachment_id: item.attachment_id || item.attachmentId || 0,
                        url: item.url || '',
                        thumbnail: item.thumbnail || item.url || '',
                        type: item.type || 'image'
                    });
                }
            });
            var cb = this.config.onSelect || function () {};
            this.close();
            cb(result);
        },

        uploadFiles: function (files) {
            var self = this;
            if (!files || !files.length) { return; }
            Array.prototype.forEach.call(files, function (file) {
                self.uploadFile(file);
            });
        },

        uploadFile: function (file) {
            var self = this;
            var formData = new FormData();
            formData.append('action', 'vmp_media_upload');
            formData.append('nonce', vmp_media.nonce);
            formData.append('file', file);
            this.$uploadBtn.prop('disabled', true).addClass('vmp-picker-busy');

            $.ajax({
                url: vmp_media.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (resp) {
                if (resp && resp.success) {
                    self.showToast(self.t('uploadSuccess') || 'Uploaded successfully', 'success');
                    self.state.page = 1;
                    self.load();
                } else {
                    self.showToast((resp && resp.data && resp.data.message) || self.t('uploadError') || 'Upload failed. Please try again.', 'error');
                }
            }).fail(function (xhr) {
                var msg = self.t('uploadError') || 'Upload failed. Please try again.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                self.showToast(msg, 'error');
            }).always(function () {
                self.$uploadBtn.prop('disabled', false).removeClass('vmp-picker-busy');
            });
        },

        askDelete: function (id) {
            var self = this;
            this.closeConfirm();
            var overlay = $('<div class="vmp-picker-confirm-overlay"></div>');
            var modal = $('<div class="vmp-picker-confirm-modal" role="dialog" aria-modal="true"></div>');
            modal.append('<p class="vmp-picker-confirm-message">' + (this.t('confirmDelete') || 'Delete this file?') + '</p>');
            var actions = $('<div class="vmp-picker-confirm-actions"></div>');
            var cancelBtn = $('<button type="button" class="button vmp-picker-confirm-cancel">' + (this.t('cancel') || 'Cancel') + '</button>');
            var okBtn = $('<button type="button" class="button button-primary vmp-picker-confirm-ok">' + (this.t('confirm') || 'Confirm') + '</button>');
            actions.append(cancelBtn).append(okBtn);
            modal.append(actions);
            overlay.append(modal);
            $('body').append(overlay);
            this.confirmEls = { overlay: overlay };

            cancelBtn.on('click', function () { self.closeConfirm(); });
            okBtn.on('click', function () {
                self.closeConfirm();
                self.deleteMedia(id);
            });
        },

        closeConfirm: function () {
            if (this.confirmEls) {
                this.confirmEls.overlay.remove();
                this.confirmEls = null;
            }
        },

        deleteMedia: function (id) {
            var self = this;
            $.post(vmp_media.ajax_url, {
                action: 'vmp_media_delete',
                media_id: id,
                nonce: vmp_media.nonce
            }, function (resp) {
                if (resp && resp.success) {
                    self.$grid.find('.vmp-picker-item[data-id="' + id + '"]').remove();
                    var idx = self.state.selection.indexOf(parseInt(id, 10));
                    if (idx >= 0) { self.state.selection.splice(idx, 1); }
                    delete self.state.itemsMap[id];
                    self.showToast(self.t('deleted') || 'Deleted successfully', 'success');
                } else {
                    self.showToast((resp && resp.data && resp.data.message) || self.t('uploadError') || 'Error', 'error');
                }
            }).fail(function () {
                self.showToast(self.t('networkError') || 'Network error. Please try again.', 'error');
            });
        },

        loadMore: function () {
            if (this.state.loading || !this.state.hasMore) { return; }
            this.state.page++;
            this.load();
        },

        filter: function () {
            var q = (this.$search.val() || '').toLowerCase().trim();
            var self = this;
            this.$grid.find('.vmp-picker-item').each(function () {
                var $item = $(this);
                var show = true;
                if (q) {
                    var type = ($item.data('type') || '').toLowerCase();
                    var attachment = String($item.data('attachment') || '');
                    show = type.indexOf(q) >= 0 || attachment.indexOf(q) >= 0;
                }
                $item.toggle(show);
            });
        },

        updateCount: function () {
            if (this.state.mode === 'multiple') {
                this.$count.text('0 / ' + this.state.total);
            } else {
                this.$count.text(this.state.total + ' ' + (this.t('files') || 'files'));
            }
        },

        cancel: function () {
            this.close();
            if (this.config.onCancel) { this.config.onCancel(); }
        },

        close: function () {
            if (this.$overlay) { this.$overlay.remove(); }
            this.$overlay = null;
            this.$modal = null;
            this.$grid = null;
            this.$done = null;
            this.$file = null;
            this.$uploadBtn = null;
            this.$search = null;
            this.$loadMore = null;
            this.$count = null;
            this.closeConfirm();
        },

        showToast: function (msg, type) {
            var $t = $('<div class="vmp-picker-toast vmp-picker-toast-' + (type || 'info') + '">').text(msg);
            $('body').append($t);
            window.requestAnimationFrame(function () { $t.addClass('visible'); });
            setTimeout(function () {
                $t.addClass('hiding');
                setTimeout(function () { $t.remove(); }, 300);
            }, 3000);
        },

        t: function (key) {
            var map = (vmp_media.i18n) || {};
            var fallback = {
                mediaLibrary: 'Media Library',
                uploadNew: 'Upload New',
                search: 'Search',
                loadMore: 'Load more',
                done: 'Done',
                delete: 'Delete',
                noMedia: 'No media found.',
                uploadError: 'Upload failed. Please try again.',
                uploadSuccess: 'Uploaded successfully.',
                networkError: 'Network error. Please try again.',
                deleted: 'Deleted successfully.',
                loadError: 'Error loading media.',
                confirmDelete: 'Are you sure you want to delete this file?',
                cancel: 'Cancel',
                confirm: 'Confirm',
                files: 'files'
            };
            return map[key] || fallback[key] || '';
        }
    };

    window.VMPMediaPicker = Picker;

})(jQuery, window);
