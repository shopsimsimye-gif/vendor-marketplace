<?php

declare(strict_types=1);

namespace VMP\Modules;

defined('ABSPATH') || exit;

use VMP\Contracts\MediaRepositoryInterface;

class Media extends AbstractModule
{
    public function init(): void
    {
        // Cleanup hooks
        add_action('delete_user', [$this, 'cleanupVendorMedia'], 10, 1);
        add_action('before_delete_post', [$this, 'cleanupProductMedia'], 10, 1);

        // Front-end hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_shortcode('vmp_vendor_media', [$this, 'renderMediaLibrary']);
    }

    public function enqueueAssets(): void
    {
        if (!is_page() && !is_singular()) {
            return;
        }

        if (!current_user_can('vmp_vendor') && !current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_style(
            'vmp-media-library',
            VMP_PLUGIN_URL . 'public/css/media-library.css',
            [],
            VMP_VERSION
        );

        wp_enqueue_script(
            'vmp-media-library',
            VMP_PLUGIN_URL . 'public/js/media-library.js',
            ['jquery'],
            VMP_VERSION,
            true
        );

        wp_localize_script('vmp-media-library', 'vmp_media', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vmp_public_nonce'),
            'user_id'  => get_current_user_id(),
            'i18n'     => [
                'selectOrUpload'   => __('Select or Upload Media', 'vendor-marketplace'),
                'useThisMedia'     => __('Use this media', 'vendor-marketplace'),
                'confirmDelete'    => __('Are you sure you want to delete this file?', 'vendor-marketplace'),
                'delete'           => __('Delete', 'vendor-marketplace'),
                'noMedia'          => __('No media files found.', 'vendor-marketplace'),
                'uploadError'      => __('Upload failed. Please try again.', 'vendor-marketplace'),
                'uploadSuccess'    => __('Uploaded successfully.', 'vendor-marketplace'),
                'networkError'     => __('Network error. Please try again.', 'vendor-marketplace'),
                'deleted'          => __('Deleted successfully.', 'vendor-marketplace'),
                'selected'         => __('Selected.', 'vendor-marketplace'),
                'cancel'           => __('Cancel', 'vendor-marketplace'),
                'confirm'          => __('Confirm', 'vendor-marketplace'),
                'mediaUnavailable' => __('Media library is not available.', 'vendor-marketplace'),
            ],
        ]);

        // [QA 2026-08-07] wp.media على الواجهة الأمامية يرفع عبر REST /wp/v2/media ويحتاج wpApiSettings.
        // core يطبع wpApiSettings في الواجهة الأمامية فقط عند استدعاء wp-api-fetch؛ نضمنه هنا fallback.
        if (wp_script_is('wp-api-fetch', 'registered') && !wp_script_is('wp-api-fetch', 'enqueued')) {
            wp_enqueue_script('wp-api-fetch');
        }
        wp_localize_script('wp-api-fetch', 'wpApiSettings', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public function renderMediaLibrary(array $atts = []): string
    {
        if (!current_user_can('vmp_vendor') && !current_user_can('manage_options')) {
            return '<p>' . esc_html__('Access denied.', 'vendor-marketplace') . '</p>';
        }

        ob_start();
        include VMP_PLUGIN_DIR . 'public/templates/vendor-media-library.php';
        return ob_get_clean();
    }

    public function cleanupVendorMedia(int $userId): void
    {
        if (!user_can($userId, 'vmp_vendor')) {
            return;
        }
        $repository = $this->make(MediaRepositoryInterface::class);
        if ($repository) {
            $repository->deleteByVendor($userId);
        }
    }

    public function cleanupProductMedia(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'product') {
            return;
        }
        delete_post_meta($postId, '_vmp_featured_media_id');
    }
}
