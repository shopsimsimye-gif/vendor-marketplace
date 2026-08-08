<?php

declare(strict_types=1);

namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Contracts\MediaRepositoryInterface;
use VMP\Services\MediaService;
use VMP\Http\Requests\UploadMediaRequest;
use VMP\Http\Requests\DeleteMediaRequest;

class MediaController extends BaseController
{
    private const DAILY_UPLOAD_LIMIT = 100;
    private const UPLOAD_WINDOW = DAY_IN_SECONDS;

    public function __construct(
        private readonly MediaService $mediaService,
        private readonly MediaRepositoryInterface $repository,
    ) {}

    public function upload(UploadMediaRequest $request): void
    {
        try {
            $vendorId = get_current_user_id();

            if ($this->isUploadLimitExceeded($vendorId)) {
                wp_send_json_error(['message' => __('Daily upload limit exceeded', 'vendor-marketplace')]);
                return;
            }

            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => __('Invalid file upload', 'vendor-marketplace')]);
                return;
            }

            $media = $this->mediaService->upload($file, $vendorId);
            $this->incrementUploadCount($vendorId);

            wp_send_json_success([
                'media' => $media->toArray(),
                'url' => wp_get_attachment_url($media->attachmentId),
                'thumbnail' => wp_get_attachment_image_url($media->attachmentId, 'thumbnail'),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function destroy(DeleteMediaRequest $request): void
    {
        try {
            $mediaId = (int) ($_POST['media_id'] ?? 0);
            $vendorId = get_current_user_id();

            $this->mediaService->delete($mediaId, $vendorId);

            wp_send_json_success(['message' => __('Media deleted successfully', 'vendor-marketplace')]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function index(): void
    {
        // [QA 2026-08-07] فحص nonce — كان غائباً (المسار AJAX no-Request لا يتحقق تلقائياً).
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $vendorId = get_current_user_id();
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 20);
            $type = sanitize_text_field($_GET['type'] ?? '');

            $filters = [];
            if ($type) {
                $filters['type'] = $type;
            }

            $result = $this->mediaService->paginate($vendorId, $page, $perPage);

            $result['data'] = array_map(function ($media) {
                $data = $media->toArray();
                $data['url'] = wp_get_attachment_url($media->attachmentId);
                $data['thumbnail'] = wp_get_attachment_image_url($media->attachmentId, 'thumbnail');
                $data['medium'] = wp_get_attachment_image_url($media->attachmentId, 'medium');
                $data['full'] = wp_get_attachment_image_url($media->attachmentId, 'full');
                return $data;
            }, $result['data']);

            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private function isUploadLimitExceeded(int $vendorId): bool
    {
        $key = "vmp_media_uploads_{$vendorId}_" . gmdate('Ymd');
        $count = (int) get_transient($key);
        return $count >= self::DAILY_UPLOAD_LIMIT;
    }

    private function incrementUploadCount(int $vendorId): void
    {
        $key = "vmp_media_uploads_{$vendorId}_" . gmdate('Ymd');
        $count = (int) get_transient($key);
        if ($count === 0) {
            set_transient($key, 1, self::UPLOAD_WINDOW);
        } else {
            set_transient($key, $count + 1, self::UPLOAD_WINDOW);
        }
    }
}
