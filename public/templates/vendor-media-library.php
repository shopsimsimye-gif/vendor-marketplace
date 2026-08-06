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
        <button type="button" id="vmp-media-select" class="button secondary">
            <span class="dashicons dashicons-format-gallery"></span>
            <?php esc_html_e('Select from WordPress', 'vendor-marketplace'); ?>
        </button>
        <div class="vmp-media-stats">
            <span id="vmp-media-count">0</span> <?php esc_html_e('files', 'vendor-marketplace'); ?>
        </div>
    </div>

    <div id="vmp-media-grid"></div>

    <button type="button" id="vmp-media-load-more" class="button" style="display:none;">
        <?php esc_html_e('Load More', 'vendor-marketplace'); ?>
    </button>
</div>
