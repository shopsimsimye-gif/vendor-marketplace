<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Services;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Contracts\MediaRepositoryInterface;
use VMP\Modules\Media\Contracts\FolderRepositoryInterface;
use VMP\Modules\Media\Contracts\StorageRepositoryInterface;
use VMP\Modules\Media\DTO\MediaDTO;
use VMP\Core\EventManager;
use VMP\Core\Logger;

class MediaService
{
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
        'video/mp4', 'video/webm',
        'application/pdf',
    ];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly StorageRepositoryInterface $storageRepository,
        private readonly EventManager $eventManager,
        private readonly Logger $logger,
    ) {}

    public function upload(array $file, int $vendorId, ?int $folderId = null): MediaDTO
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
            'post_mime_type' => $realMime,
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
            folderId: $folderId,
        );

        $media = $this->mediaRepository->create($dto);

        $this->eventManager->trigger('vmp_media_uploaded', $media);
        $this->logger->info('Media uploaded', ['media_id' => $media->id, 'vendor_id' => $vendorId]);

        return $media;
    }

    public function createFromAI(array $imageData, int $vendorId, ?int $folderId = null): ?MediaDTO
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

            $uploaded = wp_handle_sideload($fileArray, ['test_form' => false]);
            if (isset($uploaded['error']) || empty($uploaded['file'])) {
                throw new \RuntimeException($uploaded['error'] ?? __('Upload failed', 'vendor-marketplace'));
            }

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
                'post_mime_type' => $realMime,
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
                folderId: $folderId,
            );

            $media = $this->mediaRepository->create($dto);

            $this->eventManager->trigger('vmp_media_uploaded', $media);
            $this->logger->info('AI media created', ['media_id' => $media->id, 'vendor_id' => $vendorId]);

            return $media;
        } catch (\Throwable $e) {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            $this->logger->error('AI media creation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function delete(int $mediaId, int $vendorId): bool
    {
        $media = $this->mediaRepository->find($mediaId);
        if (!$media || $media->vendorId !== $vendorId) {
            throw new \RuntimeException(__('Media not found or access denied', 'vendor-marketplace'));
        }

        wp_delete_attachment($media->attachmentId, true);
        $deleted = $this->mediaRepository->delete($mediaId);

        if ($deleted) {
            $this->eventManager->trigger('vmp_media_deleted', $media);
            $this->logger->info('Media deleted', ['media_id' => $mediaId, 'vendor_id' => $vendorId]);
        }

        return $deleted;
    }

    public function getVendorMedia(int $vendorId, array $filters = []): array
    {
        return $this->mediaRepository->findByVendor($vendorId, $filters);
    }

    public function getFolderMedia(int $folderId, int $vendorId, array $filters = []): array
    {
        return $this->mediaRepository->findByFolder($folderId, $vendorId, $filters);
    }

    public function paginate(int $vendorId, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        return $this->mediaRepository->paginate($vendorId, $page, $perPage, $filters);
    }

    public function paginateByFolder(int $folderId, int $vendorId, int $page = 1, int $perPage = 20): array
    {
        return $this->mediaRepository->paginateByFolder($folderId, $vendorId, $page, $perPage);
    }

    public function count(int $vendorId): int
    {
        return $this->mediaRepository->countByVendor($vendorId);
    }

    public function moveToFolder(int $mediaId, int $folderId, int $vendorId): bool
    {
        $media = $this->mediaRepository->find($mediaId);
        if (!$media || $media->vendorId !== $vendorId) {
            throw new \RuntimeException(__('Media not found or access denied', 'vendor-marketplace'));
        }

        $folder = $this->folderRepository->find($folderId);
        if (!$folder || $folder->vendorId !== $vendorId) {
            throw new \RuntimeException(__('Folder not found or access denied', 'vendor-marketplace'));
        }

        return $this->mediaRepository->moveToFolder($mediaId, $folderId);
    }

    public function updateMedia(int $mediaId, int $vendorId, array $data): ?MediaDTO
    {
        $media = $this->mediaRepository->find($mediaId);
        if (!$media || $media->vendorId !== $vendorId) {
            return null;
        }

        if (isset($data['folder_id'])) {
            $media->folderId = (int) $data['folder_id'];
        }
        if (isset($data['type'])) {
            $media->type = $data['type'];
        }
        if (isset($data['metadata'])) {
            $media->metadata = $data['metadata'];
        }

        $this->mediaRepository->update($mediaId, $media);
        return $this->mediaRepository->find($mediaId);
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
