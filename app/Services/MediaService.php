<?php

declare(strict_types=1);

namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\MediaRepositoryInterface;
use VMP\DTO\MediaDTO;
use VMP\Core\Container;

class MediaService
{
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
        'video/mp4', 'video/webm',
        'application/pdf',
    ];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(
        private readonly MediaRepositoryInterface $repository,
    ) {}

    public function upload(array $file, int $vendorId): MediaDTO
    {
        if (($file['size'] ?? 0) > self::MAX_FILE_SIZE) {
            throw new \RuntimeException(__('File exceeds maximum size of 10MB', 'vendor-marketplace'));
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $uploaded = wp_handle_upload($file, ['test_form' => false]);
        if (isset($uploaded['error'])) {
            throw new \RuntimeException($uploaded['error']);
        }

        if (!file_exists($uploaded['file'])) {
            throw new \RuntimeException(__('Upload failed: file not found', 'vendor-marketplace'));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($uploaded['file']);
        if (!in_array($realMime, self::ALLOWED_MIMES, true)) {
            wp_delete_file($uploaded['file']);
            throw new \RuntimeException(__('Invalid file type detected', 'vendor-marketplace'));
        }

        $normalized = ['image/jpg' => 'image/jpeg', 'image/x-png' => 'image/png'];
        $declared = $normalized[$file['type']] ?? $file['type'];
        $actual = $normalized[$realMime] ?? $realMime;
        if ($declared !== $actual) {
            wp_delete_file($uploaded['file']);
            throw new \RuntimeException(__('MIME type mismatch detected', 'vendor-marketplace'));
        }

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachmentId = wp_insert_attachment([
            'post_title'   => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status'  => 'inherit',
            'post_author'  => $vendorId,
        ], $uploaded['file']);

        $metadata = wp_generate_attachment_metadata($attachmentId, $uploaded['file']);
        wp_update_attachment_metadata($attachmentId, $metadata);

        $imageSize = $this->getImageDimensions($uploaded['file'], $metadata);
        $realFileSize = filesize($uploaded['file']);

        $dto = new MediaDTO(
            vendorId: $vendorId,
            attachmentId: $attachmentId,
            type: $this->resolveType($realMime),
            mimeType: $realMime,
            fileSize: (int) $realFileSize,
            width: $imageSize['width'],
            height: $imageSize['height'],
            metadata: $metadata,
        );

        $media = $this->repository->create($dto);

        do_action('vmp_media_uploaded', $media);

        $eventManager = Container::getInstance()->get('event_manager');
        if ($eventManager) {
            $eventManager->trigger('vmp_media_uploaded', $media);
        }

        return $media;
    }

    /**
     * Save an AI-generated (or external) image into the vendor media library.
     *
     * Uses wp_handle_sideload() because the file comes from download_url(),
     * not from $_FILES (wp_handle_upload() relies on move_uploaded_file()
     * which only works for HTTP POST uploads).
     *
     * @param array $imageData ['url' => '...', 'name' => '...', 'mime' => 'image/png']
     * @param int   $vendorId  Attachment author (vendor user id).
     * @return MediaDTO|null
     */
    public function createFromAI(array $imageData, int $vendorId): ?MediaDTO
    {
        if (empty($imageData['url'])) {
            return null;
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tmpFile = download_url($imageData['url']);
        if (is_wp_error($tmpFile)) {
            return null;
        }

        try {
            $fileArray = [
                'name'     => sanitize_file_name($imageData['name'] ?? 'ai-generated.png'),
                'tmp_name' => $tmpFile,
                'type'     => $imageData['mime'] ?? 'image/png',
                'error'    => 0,
                'size'     => filesize($tmpFile) ?: 0,
            ];

            if (($fileArray['size'] ?? 0) > self::MAX_FILE_SIZE) {
                throw new \RuntimeException(__('File exceeds maximum size of 10MB', 'vendor-marketplace'));
            }

            // Sideload (copy) the downloaded temp file into the uploads dir.
            $uploaded = wp_handle_sideload($fileArray, ['test_form' => false]);
            if (isset($uploaded['error']) || empty($uploaded['file'])) {
                throw new \RuntimeException($uploaded['error'] ?? __('Upload failed', 'vendor-marketplace'));
            }

            // Real MIME check (finfo) — reject anything outside ALLOWED_MIMES.
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($uploaded['file']);
            if (!in_array($realMime, self::ALLOWED_MIMES, true)) {
                wp_delete_file($uploaded['file']);
                throw new \RuntimeException(__('Invalid file type detected', 'vendor-marketplace'));
            }

            if (!function_exists('wp_generate_attachment_metadata')) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }

            $attachmentId = wp_insert_attachment([
                'post_title'   => $fileArray['name'],
                'post_content' => '',
                'post_status'  => 'inherit',
                'post_author'  => $vendorId,
            ], $uploaded['file']);

            if (is_wp_error($attachmentId)) {
                wp_delete_file($uploaded['file']);
                throw new \RuntimeException($attachmentId->get_error_message());
            }

            $metadata = wp_generate_attachment_metadata($attachmentId, $uploaded['file']);
            wp_update_attachment_metadata($attachmentId, $metadata);

            $imageSize = $this->getImageDimensions($uploaded['file'], $metadata);
            $realFileSize = filesize($uploaded['file']) ?: 0;

            $dto = new MediaDTO(
                vendorId: $vendorId,
                attachmentId: (int) $attachmentId,
                type: $this->resolveType($realMime),
                mimeType: $realMime,
                fileSize: (int) $realFileSize,
                width: $imageSize['width'],
                height: $imageSize['height'],
                metadata: $metadata,
            );

            $media = $this->repository->create($dto);

            do_action('vmp_media_uploaded', $media);

            $eventManager = Container::getInstance()->get('event_manager');
            if ($eventManager) {
                $eventManager->trigger('vmp_media_uploaded', $media);
            }

            return $media;
        } catch (\Throwable $e) {
            // Clean up any leftover downloaded temp file.
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            return null;
        }
    }

    public function selectAttachment(int $attachmentId, int $vendorId): MediaDTO
    {
        $existing = $this->repository->findByAttachment($attachmentId);
        if ($existing) {
            return $existing;
        }

        $attachment = get_post($attachmentId);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            throw new \RuntimeException(__('Invalid attachment', 'vendor-marketplace'));
        }

        $filePath = get_attached_file($attachmentId);
        $metadata = wp_get_attachment_metadata($attachmentId);
        $mimeType = get_post_mime_type($attachmentId);
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        $imageSize = $this->getImageDimensions($filePath, $metadata);

        $dto = new MediaDTO(
            vendorId: $vendorId,
            attachmentId: $attachmentId,
            type: $this->resolveType($mimeType),
            mimeType: $mimeType,
            fileSize: (int) $fileSize,
            width: $imageSize['width'],
            height: $imageSize['height'],
            metadata: $metadata,
        );

        return $this->repository->create($dto);
    }

    public function delete(int $mediaId, int $vendorId): bool
    {
        $media = $this->repository->find($mediaId);
        if (!$media || $media->vendorId !== $vendorId) {
            throw new \RuntimeException(__('Media not found or access denied', 'vendor-marketplace'));
        }

        wp_delete_attachment($media->attachmentId, true);
        $deleted = $this->repository->delete($mediaId);

        if ($deleted) {
            do_action('vmp_media_deleted', $media);
            $eventManager = Container::getInstance()->get('event_manager');
            if ($eventManager) {
                $eventManager->trigger('vmp_media_deleted', $media);
            }
        }

        return $deleted;
    }

    public function getVendorMedia(int $vendorId, array $filters = []): array
    {
        return $this->repository->findByVendor($vendorId, $filters);
    }

    public function paginate(int $vendorId, int $page = 1, int $perPage = 20): array
    {
        return $this->repository->paginate($vendorId, $page, $perPage);
    }

    public function count(int $vendorId): int
    {
        return $this->repository->countByVendor($vendorId);
    }

    private function resolveType(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') === 0) return 'image';
        if (strpos($mimeType, 'video/') === 0) return 'video';
        if (in_array($mimeType, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true)) {
            return 'document';
        }
        return 'other';
    }

    private function getImageDimensions(?string $filePath, ?array $metadata): array
    {
        if (!empty($metadata['width']) && !empty($metadata['height'])) {
            return ['width' => (int) $metadata['width'], 'height' => (int) $metadata['height']];
        }
        if ($filePath && file_exists($filePath)) {
            $dims = getimagesize($filePath);
            if ($dims) return ['width' => $dims[0], 'height' => $dims[1]];
        }
        return ['width' => null, 'height' => null];
    }
}
