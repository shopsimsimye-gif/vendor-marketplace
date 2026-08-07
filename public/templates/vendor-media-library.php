<?php
/**
 * Template: Vendor Media Library
 *
 * @package VendorMarketplace
 */

defined('ABSPATH') || exit;

if (!current_user_can('vmp_vendor') && !current_user_can('manage_options')) {
    wp_die(esc_html__('Access denied.', 'vendor-marketplace'));
}

$vendor_id = get_current_user_id();
?>

<div id="vmp-media-library" class="wrap">
    <h1><?php echo esc_html__('Media Library', 'vendor-marketplace'); ?></h1>

    <div class="vmp-media-toolbar">
        <button type="button" id="vmp-media-upload" class="button button-primary">
            <span class="dashicons dashicons-upload"></span>
            <?php esc_html_e('Upload New', 'vendor-marketplace'); ?>
        </button>
        <div class="vmp-media-stats">
            <span id="vmp-media-count">0</span> <?php esc_html_e('files', 'vendor-marketplace'); ?>
        </div>
    </div>

    <!-- [QA 2026-08-07] Hidden file input for direct upload via vmp_media_upload.
         Bypasses wp.media/plupload/async-upload.php entirely. -->
    <input type="file" id="vmp-media-file-input" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,application/pdf" style="display:none;">

    <div id="vmp-media-grid"></div>

    <button type="button" id="vmp-media-load-more" class="button" style="display:none;">
        <?php esc_html_e('Load More', 'vendor-marketplace'); ?>
    </button>
</div>
