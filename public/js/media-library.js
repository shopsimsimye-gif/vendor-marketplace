/**
 * VMP Vendor Media Library
 * Depends: wp.media, jQuery
 * Globals: vmp_media (localized via MediaModule::enqueueAssets)
 */
(function($) {
    'use strict';

    // Guard: bail if localized data missing
    if (typeof vmp_media === 'undefined') {
        window.vmp_media = {
            ajax_url: window.vmp_public && vmp_public.ajax_url ? vmp_public.ajax_url : (window.ajaxurl || ''),
            nonce: window.vmp_public && vmp_public.nonce ? vmp_public.nonce : '',
            i18n: {
                selectOrUpload: 'Select or Upload Media',
                useThisMedia: 'Use this media',
                confirmDelete: 'Are you sure you want to delete this file?',
                delete: 'Delete',
                noMedia: 'No media files found.'
            }
        };
    }

    const VMPMedia = {
        page: 1,
        perPage: 20,
        loading: false,
        hasMore: true,

        init() {
            this.cacheDOM();
            this.bindEvents();
            this.loadMedia();
        },

        cacheDOM() {
            this.$grid = $('#vmp-media-grid');
            this.$uploadBtn = $('#vmp-media-upload');
            this.$selectBtn = $('#vmp-media-select');
            this.$loadMore = $('#vmp-media-load-more');
            this.$count = $('#vmp-media-count');
            this.frame = null;
        },

        bindEvents() {
            this.$uploadBtn.on('click', (e) => {
                e.preventDefault();
                this.openUploader();
            });

            this.$selectBtn.on('click', (e) => {
                e.preventDefault();
                this.openMediaFrame();
            });

            this.$grid.on('click', '.vmp-media-delete', (e) => {
                e.stopPropagation();
                const $item = $(e.currentTarget).closest('.vmp-media-item');
                const id = $item.data('id');
                if (confirm(vmp_media.i18n.confirmDelete)) {
                    this.deleteMedia(id, $item);
                }
            });

            this.$grid.on('click', '.vmp-media-item', (e) => {
                const $item = $(e.currentTarget).closest('.vmp-media-item');
                this.selectMedia($item.data('id'), $item.find('img').attr('src'));
            });

            this.$loadMore.on('click', (e) => {
                e.preventDefault();
                this.loadMore();
            });
        },

        openUploader() {
            if (this.frame) {
                this.frame.open();
                return;
            }

            this.frame = wp.media({
                title: vmp_media.i18n.selectOrUpload,
                button: { text: vmp_media.i18n.useThisMedia },
                multiple: false,
                library: { type: 'image' }
            });

            this.frame.on('select', () => {
                const attachment = this.frame.state().get('selection').first().toJSON();
                this.uploadViaAjax(attachment);
            });

            this.frame.open();
        },

        openMediaFrame() {
            this.openUploader();
        },

        uploadViaAjax(attachment) {
            this.setLoading(true);

            const data = {
                action: 'vmp_media_select',
                attachment_id: attachment.id,
                nonce: vmp_media.nonce
            };

            $.post(vmp_media.ajax_url, data, (response) => {
                this.setLoading(false);
                if (response.success) {
                    this.prependItem(response.data.media);
                    this.updateCount(+1);
                } else {
                    alert(response.data.message || 'Error');
                }
            }).fail(() => {
                this.setLoading(false);
                alert('Network error');
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
                if (response.success) {
                    this.renderItems(response.data.data);
                    this.hasMore = response.data.page < response.data.total_pages;
                    this.$loadMore.toggle(this.hasMore);
                    this.updateCount(response.data.total);
                }
            }).fail(() => {
                this.setLoading(false);
            });
        },

        renderItems(items) {
            if (!items || !items.length) {
                if (this.page === 1) {
                    this.$grid.html(`<p class="vmp-media-empty">${vmp_media.i18n.noMedia}</p>`);
                }
                return;
            }

            items.forEach(item => {
                this.$grid.append(this.itemTemplate(item));
            });
        },

        prependItem(item) {
            const $el = $(this.itemTemplate(item));
            this.$grid.prepend($el);
            $el.hide().slideDown(300);
        },

        itemTemplate(item) {
            const thumb = item.thumbnail || item.url || '';
            const typeClass = item.type || 'image';
            return `
                <div class="vmp-media-item" data-id="${item.id}" data-attachment="${item.attachment_id}">
                    <div class="vmp-media-thumb">
                        <img src="${thumb}" alt="" loading="lazy">
                        <span class="vmp-media-type">${typeClass}</span>
                    </div>
                    <div class="vmp-media-meta">
                        <span class="vmp-media-size">${this.formatBytes(item.file_size)}</span>
                        <button class="vmp-media-delete" title="${vmp_media.i18n.delete}">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;
        },

        deleteMedia(id, $element) {
            this.setLoading(true);

            $.post(vmp_media.ajax_url, {
                action: 'vmp_media_delete',
                media_id: id,
                nonce: vmp_media.nonce
            }, (response) => {
                this.setLoading(false);
                if (response.success) {
                    $element.fadeOut(300, () => {
                        $element.remove();
                        this.updateCount(-1);
                    });
                } else {
                    alert(response.data.message || 'Error');
                }
            }).fail(() => {
                this.setLoading(false);
            });
        },

        selectMedia(id, url) {
            $(document).trigger('vmp:media:selected', { id, url });
        },

        setLoading(state) {
            this.loading = state;
            this.$grid.toggleClass('vmp-media-loading', state);
        },

        updateCount(delta) {
            const current = parseInt(this.$count.text()) || 0;
            this.$count.text(Math.max(0, current + delta));
        },

        formatBytes(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    };

    $(document).ready(() => {
        if ($('#vmp-media-library').length) {
            VMPMedia.init();
        }
    });

    window.VMPMedia = VMPMedia;

})(jQuery);
