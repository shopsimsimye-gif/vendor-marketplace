<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Http\Controllers\Api;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Services\MediaService;
use VMP\Modules\Media\Services\FolderService;
use VMP\Modules\Media\Services\StorageService;
use VMP\Modules\Media\Policies\MediaPolicy;
use VMP\Infrastructure\Dispatcher\RouteRegistry;

class MediaApiController
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly StorageService $storageService,
        private readonly FolderService $folderService,
        private readonly MediaPolicy $policy,
        private readonly ?RouteRegistry $routeRegistry = null,
    ) {}

    public function registerRoutes(\VMP\Infrastructure\Dispatcher\RouteRegistry $registry = null): void
    {
        if ($registry === null) {
            $registry = $this->routeRegistry;
        }

        if ($registry === null) {
            return;
        }

        $controllerClass = self::class;

        // Media endpoints
        $registry->ajax('vmp_media_upload',   $controllerClass, 'upload',   false, 'vmp_public_nonce');
        $registry->ajax('vmp_media_delete',   $controllerClass, 'delete',   false, 'vmp_public_nonce');
        $registry->ajax('vmp_media_list',     $controllerClass, 'list',     false, 'vmp_public_nonce');
        $registry->ajax('vmp_media_move',     $controllerClass, 'move',     false, 'vmp_public_nonce');
        $registry->ajax('vmp_media_update',   $controllerClass, 'update',   false, 'vmp_public_nonce');

        // Folder endpoints
        $registry->ajax('vmp_folder_create',  $controllerClass, 'createFolder',  false, 'vmp_public_nonce');
        $registry->ajax('vmp_folder_update',  $controllerClass, 'updateFolder',  false, 'vmp_public_nonce');
        $registry->ajax('vmp_folder_delete',  $controllerClass, 'deleteFolder',  false, 'vmp_public_nonce');
        $registry->ajax('vmp_folder_list',    $controllerClass, 'listFolders',   false, 'vmp_public_nonce');
        $registry->ajax('vmp_folder_tree',    $controllerClass, 'getTree',       false, 'vmp_public_nonce');
        $registry->ajax('vmp_folder_move',    $controllerClass, 'moveFolder',    false, 'vmp_public_nonce');
        $registry->ajax('vmp_folder_breadcrumb', $controllerClass, 'getBreadcrumbs', false, 'vmp_public_nonce');

        // Storage endpoints
        $registry->ajax('vmp_storage_create', $controllerClass, 'createStorage', false, 'vmp_public_nonce');
        $registry->ajax('vmp_storage_update', $controllerClass, 'updateStorage', false, 'vmp_public_nonce');
        $registry->ajax('vmp_storage_delete', $controllerClass, 'deleteStorage', false, 'vmp_public_nonce');
        $registry->ajax('vmp_storage_list',   $controllerClass, 'listStorages',  false, 'vmp_public_nonce');
        $registry->ajax('vmp_storage_test',   $controllerClass, 'testStorage',   false, 'vmp_public_nonce');
        $registry->ajax('vmp_storage_types',  $controllerClass, 'getStorageTypes', true);
    }

    // ========== Media Endpoints ==========

    public function upload(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $vendorId = get_current_user_id();

            if (!$this->policy->canUpload($vendorId, $vendorId)) {
                wp_send_json_error(['message' => __('Access denied', 'vendor-marketplace')]);
                return;
            }

            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => __('Invalid file upload', 'vendor-marketplace')]);
                return;
            }

            $folderId = isset($_POST['folder_id']) ? (int) $_POST['folder_id'] : null;
            if ($folderId === 0) $folderId = null;

            $media = $this->mediaService->upload($file, $vendorId, $folderId);

            wp_send_json_success([
                'media' => $media->toArray(),
                'url' => wp_get_attachment_url($media->attachmentId),
                'thumbnail' => wp_get_attachment_image_url($media->attachmentId, 'thumbnail'),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $mediaId = (int) ($_POST['media_id'] ?? 0);
            $vendorId = get_current_user_id();

            if (!$this->policy->canDelete($vendorId, $vendorId)) {
                wp_send_json_error(['message' => __('Access denied', 'vendor-marketplace')]);
                return;
            }

            $this->mediaService->delete($mediaId, $vendorId);

            wp_send_json_success(['message' => __('Media deleted successfully', 'vendor-marketplace')]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function list(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $vendorId = get_current_user_id();
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 20);
            $type = sanitize_text_field($_GET['type'] ?? '');
            $folderId = isset($_GET['folder_id']) ? (int) $_GET['folder_id'] : null;

            $filters = [];
            if ($type) {
                $filters['type'] = $type;
            }

            if ($folderId !== null && $folderId > 0) {
                $result = $this->mediaService->paginateByFolder($folderId, $vendorId, $page, $perPage);
            } else {
                $result = $this->mediaService->paginate($vendorId, $page, $perPage, $filters);
            }

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

    public function move(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $mediaId = (int) ($_POST['media_id'] ?? 0);
            $folderId = (int) ($_POST['folder_id'] ?? 0);
            $vendorId = get_current_user_id();

            if (!$this->policy->canUpload($vendorId, $vendorId)) {
                wp_send_json_error(['message' => __('Access denied', 'vendor-marketplace')]);
                return;
            }

            $this->mediaService->moveToFolder($mediaId, $folderId, $vendorId);

            wp_send_json_success(['message' => __('Media moved successfully', 'vendor-marketplace')]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function update(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $mediaId = (int) ($_POST['media_id'] ?? 0);
            $vendorId = get_current_user_id();
            $data = [];

            if (isset($_POST['folder_id'])) {
                $data['folder_id'] = (int) $_POST['folder_id'];
            }
            if (isset($_POST['type'])) {
                $data['type'] = sanitize_text_field($_POST['type']);
            }
            if (isset($_POST['metadata'])) {
                $data['metadata'] = json_decode(stripslashes($_POST['metadata']), true);
            }

            $media = $this->mediaService->updateMedia($mediaId, $vendorId, $data);

            if (!$media) {
                wp_send_json_error(['message' => __('Media not found or access denied', 'vendor-marketplace')]);
                return;
            }

            wp_send_json_success(['media' => $media->toArray()]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    // ========== Folder Endpoints ==========

    public function createFolder(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $vendorId = get_current_user_id();
            $name = sanitize_text_field($_POST['name'] ?? '');
            $parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

            if (empty($name)) {
                wp_send_json_error(['message' => __('Folder name is required', 'vendor-marketplace')]);
                return;
            }

            if ($parentId === 0) $parentId = null;

            $folder = $this->folderService->create($vendorId, $name, $parentId);

            wp_send_json_success(['folder' => $folder->toArray()]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function updateFolder(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $folderId = (int) ($_POST['folder_id'] ?? 0);
            $vendorId = get_current_user_id();
            $data = [];

            if (isset($_POST['name'])) {
                $data['name'] = sanitize_text_field($_POST['name']);
            }
            if (isset($_POST['parent_id'])) {
                $data['parent_id'] = (int) $_POST['parent_id'];
            }
            if (isset($_POST['sort_order'])) {
                $data['sort_order'] = (int) $_POST['sort_order'];
            }

            $folder = $this->folderService->update($folderId, $vendorId, $data);

            if (!$folder) {
                wp_send_json_error(['message' => __('Folder not found or access denied', 'vendor-marketplace')]);
                return;
            }

            wp_send_json_success(['folder' => $folder->toArray()]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function deleteFolder(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $folderId = (int) ($_POST['folder_id'] ?? 0);
            $vendorId = get_current_user_id();

            $this->folderService->delete($folderId, $vendorId);

            wp_send_json_success(['message' => __('Folder deleted successfully', 'vendor-marketplace')]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function listFolders(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $vendorId = get_current_user_id();
            $parentId = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0;

            $folders = $this->folderService->getFlat($vendorId);

            wp_send_json_success(['folders' => array_map(fn($f) => $f->toArray(), $folders)]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function getTree(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $vendorId = get_current_user_id();
            $tree = $this->folderService->getTree($vendorId);

            wp_send_json_success(['tree' => $tree]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function moveFolder(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $folderId = (int) ($_POST['folder_id'] ?? 0);
            $vendorId = get_current_user_id();
            $newParentId = (int) ($_POST['new_parent_id'] ?? 0);

            $result = $this->folderService->move($folderId, $vendorId, $newParentId);

            if (!$result) {
                wp_send_json_error(['message' => __('Cannot move folder (circular reference or invalid parent)', 'vendor-marketplace')]);
                return;
            }

            wp_send_json_success(['message' => __('Folder moved successfully', 'vendor-marketplace')]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function getBreadcrumbs(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $folderId = (int) ($_GET['folder_id'] ?? 0);
            $breadcrumbs = $this->folderService->getBreadcrumbs($folderId);

            wp_send_json_success(['breadcrumbs' => array_map(fn($f) => $f->toArray(), $breadcrumbs)]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    // ========== Storage Endpoints ==========

    public function createStorage(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $vendorId = get_current_user_id();
            $type = sanitize_text_field($_POST['type'] ?? '');
            $name = sanitize_text_field($_POST['name'] ?? '');
            $config = json_decode(stripslashes($_POST['config'] ?? '{}'), true);
            $isDefault = isset($_POST['is_default']) && (bool) $_POST['is_default'];

            if (empty($type) || empty($name)) {
                wp_send_json_error(['message' => __('Type and name are required', 'vendor-marketplace')]);
                return;
            }

            $storage = $this->storageService->create($vendorId, $type, $name, $config, $isDefault);

            wp_send_json_success(['storage' => $storage->toArray()]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function updateStorage(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $storageId = (int) ($_POST['storage_id'] ?? 0);
            $vendorId = get_current_user_id();
            $data = [];

            if (isset($_POST['name'])) {
                $data['name'] = sanitize_text_field($_POST['name']);
            }
            if (isset($_POST['config'])) {
                $data['config'] = json_decode(stripslashes($_POST['config']), true);
            }
            if (isset($_POST['is_active'])) {
                $data['is_active'] = (bool) $_POST['is_active'];
            }
            if (isset($_POST['is_default'])) {
                $data['is_default'] = (bool) $_POST['is_default'];
            }

            $storage = $this->storageService->update($storageId, $vendorId, $data);

            if (!$storage) {
                wp_send_json_error(['message' => __('Storage not found or access denied', 'vendor-marketplace')]);
                return;
            }

            wp_send_json_success(['storage' => $storage->toArray()]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function deleteStorage(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $storageId = (int) ($_POST['storage_id'] ?? 0);
            $vendorId = get_current_user_id();

            $result = $this->storageService->delete($storageId, $vendorId);

            if (!$result) {
                wp_send_json_error(['message' => __('Storage not found or access denied', 'vendor-marketplace')]);
                return;
            }

            wp_send_json_success(['message' => __('Storage deleted successfully', 'vendor-marketplace')]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function listStorages(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $vendorId = get_current_user_id();
            $storages = $this->storageService->getAll();

            // Filter by vendor if needed
            $storages = array_filter($storages, fn($s) => $s->vendorId === $vendorId || $s->vendorId === 0);

            wp_send_json_success(['storages' => array_map(fn($s) => $s->toArray(), $storages)]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function testStorage(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        try {
            $storageId = (int) ($_POST['storage_id'] ?? 0);
            $vendorId = get_current_user_id();

            $storage = null;
            foreach ($this->storageService->getAll() as $s) {
                if ($s->id === $storageId && ($s->vendorId === $vendorId || $s->vendorId === 0)) {
                    $storage = $s;
                    break;
                }
            }

            if (!$storage) {
                wp_send_json_error(['message' => __('Storage not found or access denied', 'vendor-marketplace')]);
                return;
            }

            $result = $this->storageService->testConnection($storage);

            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function getStorageTypes(): void
    {
        $types = $this->storageService->getSupportedTypes();
        wp_send_json_success(['types' => $types]);
    }
}
