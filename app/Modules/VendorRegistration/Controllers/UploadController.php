<?php
namespace VMP\Modules\VendorRegistration\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use VMP\Modules\VendorRegistration\Repositories\StoreSetupSessionRepository;
use VMP\Modules\VendorRegistration\Repositories\StoreSetupSessionRepositoryInterface;
use VMP\Modules\VendorRegistration\Services\ActivityLogService;

class UploadController
{
    private StoreSetupSessionRepositoryInterface $sessionsRepo;
    private ActivityLogService $logger;

    public function __construct(StoreSetupSessionRepositoryInterface $sessionsRepo, ActivityLogService $logger)
    {
        $this->sessionsRepo = $sessionsRepo;
        $this->logger = $logger;
    }

    public function uploadLogo(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handleUpload($request, 'logo');
    }

    public function uploadBanner(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handleUpload($request, 'banner');
    }

    public function deleteLogo(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handleDelete($request, 'logo');
    }

    public function deleteBanner(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handleDelete($request, 'banner');
    }

    private function handleUpload(WP_REST_Request $request, string $which): WP_REST_Response
    {
        // Permission
        if (!is_user_logged_in()) return new WP_REST_Response(['success'=>false,'error'=>'unauthenticated'], 401);

        $session_uuid = $request->get_header('X-Session-UUID') ?: $request->get_param('session_uuid');
        if (!$session_uuid) return new WP_REST_Response(['success'=>false,'error'=>'session_required'], 400);

        $session = $this->sessionsRepo->findByUuid($session_uuid);
        if (!$session) return new WP_REST_Response(['success'=>false,'error'=>'session_not_found'], 404);

        // Ownership
        $current = get_current_user_id();
        if ((int)$session->user_id !== (int)$current && !current_user_can('manage_vmp_requests')) {
            return new WP_REST_Response(['success'=>false,'error'=>'forbidden'], 403);
        }

        // Rate limiting: 5 per minute per user
        $rate_key = 'vmp_upload_count_' . $current;
        $count = (int) get_transient($rate_key);
        if ($count >= 5) {
            return new WP_REST_Response(['success'=>false,'error'=>'rate_limit_exceeded'], 429);
        }
        set_transient($rate_key, $count + 1, MINUTE_IN_SECONDS);

        $files = $request->get_file_params();
        if (empty($files) || empty($files['file'])) return new WP_REST_Response(['success'=>false,'error'=>'no_file'], 400);

        $file = $files['file'];

        // validate mime; allow jpeg, png, webp only
        $allowed_mimes = ['image/jpeg','image/png','image/webp'];
        if (!in_array($file['type'], $allowed_mimes, true)) return new WP_REST_Response(['success'=>false,'error'=>'invalid_mime'], 422);

        // size limit: 5MB
        $max = 5 * 1024 * 1024;
        if ($file['size'] > $max) return new WP_REST_Response(['success'=>false,'error'=>'file_too_large'], 413);

        // check dimensions before moving
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return new WP_REST_Response(['success'=>false,'error'=>'invalid_image'], 422);
        [$width, $height] = [$info[0], $info[1]];

        if ($which === 'logo') {
            // logo constraints
            if ($width < 256 || $height < 256) return new WP_REST_Response(['success'=>false,'error'=>'dimensions_too_small','details'=>'logo_min_256'], 422);
        } else {
            // banner constraints
            if ($width < 1200 || $height < 400) return new WP_REST_Response(['success'=>false,'error'=>'dimensions_too_small','details'=>'banner_min_1200x400'], 422);
            // check aspect ratio approx 3:1 at minimum
            $ratio = $width / max(1, $height);
            if ($ratio < 2.8) return new WP_REST_Response(['success'=>false,'error'=>'aspect_ratio_invalid','details'=>'expected_approx_3_1'], 422);
        }

        global $wpdb;

        // Duplicate detection by hash (compute hash from tmp file)
        $hash = hash_file('sha256', $file['tmp_name']);
        if ($hash) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", 'vmp_hash', $hash));
            if ($existing) {
                // reuse existing attachment
                $attach_id = (int) $existing;

                // Update session payload to reference existing
                $payload = json_decode($session->payload, true) ?: [];
                $payload['branding'] = $payload['branding'] ?? [];
                $old = $payload['branding'][$which] ?? null;
                $payload['branding'][$which] = $attach_id;
                $this->sessionsRepo->saveStep((int)$session->id, (int)$session->current_step ?: 2, ['branding' => $payload['branding']]);

                $this->logger->log((int)$current, ucfirst($which) . ' uploaded (deduplicated)', ['attachment_id' => $attach_id, 'session_id' => $session->id]);

                $url = wp_get_attachment_url($attach_id);
                $sizes = [];
                $meta = wp_get_attachment_metadata($attach_id);
                if (!empty($meta['sizes'])) {
                    foreach ($meta['sizes'] as $size => $data) {
                        $sizes[$size] = wp_get_attachment_image_url($attach_id, $size);
                    }
                }

                return new WP_REST_Response(['success'=>true,'attachment_id'=>$attach_id,'url'=>$url,'sizes'=>$sizes], 200);
            }
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = ['test_form'=>false];
        $movefile = wp_handle_upload($file, $overrides);
        if (isset($movefile['error'])) return new WP_REST_Response(['success'=>false,'error'=>'upload_failed','details'=>$movefile['error']], 500);

        $filename = $movefile['file'];
        $filetype = wp_check_filetype_and_ext($filename, $movefile['url']);

        // If crop params were provided (hybrid flow), crop server-side
        $crop = $request->get_param('crop');
        if ($crop && is_array($crop)) {
            $editor = wp_get_image_editor($filename);
            if (!is_wp_error($editor)) {
                // expected crop array: [x, y, width, height]
                $cx = (int) ($crop['x'] ?? 0);
                $cy = (int) ($crop['y'] ?? 0);
                $cw = (int) ($crop['width'] ?? 0);
                $ch = (int) ($crop['height'] ?? 0);
                if ($cw > 0 && $ch > 0) {
                    $editor->crop($cx, $cy, $cw, $ch);
                    $saved = $editor->save($filename);
                    if (!is_wp_error($saved)) {
                        // regenerate metadata later
                    }
                }
            }
        }

        // Create attachment
        $attachment = [
            'post_mime_type' => $filetype['type'] ?? $file['type'],
            'post_title' => sanitize_file_name(basename($filename)),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        $attach_id = wp_insert_attachment($attachment, $filename);
        if (is_wp_error($attach_id) || !$attach_id) {
            return new WP_REST_Response(['success'=>false,'error'=>'attach_failed'], 500);
        }

        // store hash meta
        if ($hash) add_post_meta($attach_id, 'vmp_hash', $hash, true);

        // Generate metadata (which will create sizes)
        $meta = wp_generate_attachment_metadata($attach_id, $filename);
        wp_update_attachment_metadata($attach_id, $meta);

        // Try to strip EXIF by re-saving via image editor (best effort)
        $editor = wp_get_image_editor($filename);
        if (!is_wp_error($editor)) {
            $saved = $editor->save($filename);
            if (!is_wp_error($saved)) {
                // regenerate metadata
                $meta = wp_generate_attachment_metadata($attach_id, $filename);
                wp_update_attachment_metadata($attach_id, $meta);
            }
        }

        // Update session payload: store attachment id under payload.branding.logo or banner
        $payload = json_decode($session->payload, true) ?: [];
        $payload['branding'] = $payload['branding'] ?? [];
        $old = $payload['branding'][$which] ?? null;
        if ($which === 'logo') {
            $payload['branding']['logo'] = $attach_id;
        } else {
            $payload['branding']['banner'] = $attach_id;
        }

        // save payload via repository
        $this->sessionsRepo->saveStep((int)$session->id, (int)$session->current_step ?: 2, ['branding' => $payload['branding']]);

        // remove old attachment if it's not referenced anywhere else
        if (!empty($old)) {
            $cnt = $this->sessionsRepo->countPayloadReferences((int)$old);
            if ($cnt <= 1) { // only referenced here
                wp_delete_attachment((int)$old, true);
            }
        }

        // Audit log
        $this->logger->log((int)get_current_user_id(), ucfirst($which) . ' uploaded', ['attachment_id' => $attach_id, 'session_id' => $session->id]);

        $res = [
            'success' => true,
            'attachment_id' => $attach_id,
            'url' => wp_get_attachment_url($attach_id),
            'sizes' => [],
        ];
        if (!empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $size => $data) {
                $res['sizes'][$size] = wp_get_attachment_image_url($attach_id, $size);
            }
        }

        return new WP_REST_Response($res, 201);
    }

    private function handleDelete(WP_REST_Request $request, string $which): WP_REST_Response
    {
        if (!is_user_logged_in()) return new WP_REST_Response(['success'=>false,'error'=>'unauthenticated'], 401);
        $session_uuid = $request->get_header('X-Session-UUID') ?: $request->get_param('session_uuid');
        if (!$session_uuid) return new WP_REST_Response(['success'=>false,'error'=>'session_required'], 400);
        $session = $this->sessionsRepo->findByUuid($session_uuid);
        if (!$session) return new WP_REST_Response(['success'=>false,'error'=>'session_not_found'], 404);
        $current = get_current_user_id();
        if ((int)$session->user_id !== (int)$current && !current_user_can('manage_vmp_requests')) {
            return new WP_REST_Response(['success'=>false,'error'=>'forbidden'], 403);
        }

        $payload = json_decode($session->payload, true) ?: [];
        if (empty($payload['branding'][$which])) return new WP_REST_Response(['success'=>false,'error'=>'not_found'], 404);
        $att = (int)$payload['branding'][$which];

        // check references elsewhere
        $cnt = $this->sessionsRepo->countPayloadReferences($att);
        if ($cnt <= 1) {
            wp_delete_attachment($att, true);
        }

        // remove from payload
        unset($payload['branding'][$which]);
        $this->sessionsRepo->saveStep((int)$session->id, (int)$session->current_step ?: 2, ['branding' => $payload['branding']]);

        $this->logger->log($current, ucfirst($which) . ' deleted', ['attachment_id'=>$att, 'session_id'=>$session->id]);

        return new WP_REST_Response(['success'=>true], 200);
    }
}
