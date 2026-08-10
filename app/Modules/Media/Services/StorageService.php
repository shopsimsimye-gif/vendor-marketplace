<?php

declare(strict_types=1);

namespace VMP\Modules\Media\Services;

defined('ABSPATH') || exit;

use VMP\Modules\Media\Contracts\StorageRepositoryInterface;
use VMP\Modules\Media\Contracts\MediaRepositoryInterface;
use VMP\Modules\Media\DTO\StorageConfigDTO;
use VMP\Core\Logger;

class StorageService
{
    private const SUPPORTED_TYPES = [
        'local' => 'Local Storage',
        's3' => 'Amazon S3',
        'wasabi' => 'Wasabi',
        'r2' => 'Cloudflare R2',
        'digitalocean' => 'DigitalOcean Spaces',
        'google_cloud' => 'Google Cloud Storage',
        'azure_blob' => 'Azure Blob Storage',
    ];

    public function __construct(
        private readonly StorageRepositoryInterface $storageRepository,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly Logger $logger,
    ) {}

    public function create(int $vendorId, string $type, string $name, array $config, bool $isDefault = false): StorageConfigDTO
    {
        if (!isset(self::SUPPORTED_TYPES[$type])) {
            throw new \InvalidArgumentException(__('Unsupported storage type', 'vendor-marketplace'));
        }

        $dto = new StorageConfigDTO(
            vendorId: $vendorId,
            type: $type,
            name: $name,
            config: $config,
            isDefault: $isDefault,
        );

        $storage = $this->storageRepository->create($dto);

        $this->logger->info('Storage config created', ['storage_id' => $storage->id, 'vendor_id' => $vendorId, 'type' => $type]);

        return $storage;
    }

    public function update(int $storageId, int $vendorId, array $data): ?StorageConfigDTO
    {
        $storage = $this->storageRepository->find($storageId);
        if (!$storage || $storage->vendorId !== $vendorId) {
            return null;
        }

        if (isset($data['name'])) {
            $storage->name = $data['name'];
        }
        if (isset($data['config'])) {
            $storage->config = array_merge($storage->config, $data['config']);
        }
        if (isset($data['is_active'])) {
            $storage->isActive = (bool) $data['is_active'];
        }
        if (isset($data['is_default'])) {
            $storage->isDefault = (bool) $data['is_default'];
        }

        $this->storageRepository->update($storageId, $storage);

        $this->logger->info('Storage config updated', ['storage_id' => $storageId, 'vendor_id' => $vendorId]);

        return $this->storageRepository->find($storageId);
    }

    public function delete(int $storageId, int $vendorId): bool
    {
        $storage = $this->storageRepository->find($storageId);
        if (!$storage || $storage->vendorId !== $vendorId) {
            return false;
        }

        // Check if storage is in use
        $mediaUsing = $this->mediaRepository->findByVendor($vendorId);
        foreach ($mediaUsing as $media) {
            // Would need to check if media uses this storage - simplified for now
        }

        $deleted = $this->storageRepository->delete($storageId);

        if ($deleted) {
            $this->logger->info('Storage config deleted', ['storage_id' => $storageId, 'vendor_id' => $vendorId]);
        }

        return $deleted;
    }

    public function getForVendor(int $vendorId): ?StorageConfigDTO
    {
        return $this->storageRepository->findByVendor($vendorId);
    }

    public function getDefault(): ?StorageConfigDTO
    {
        return $this->storageRepository->findDefault();
    }

    public function getAll(): array
    {
        return $this->storageRepository->getAll();
    }

    public function getSupportedTypes(): array
    {
        return self::SUPPORTED_TYPES;
    }

    public function testConnection(StorageConfigDTO $storage): array
    {
        switch ($storage->type) {
            case 's3':
            case 'wasabi':
            case 'r2':
            case 'digitalocean':
                return $this->testS3Compatible($storage);
            case 'google_cloud':
                return $this->testGoogleCloud($storage);
            case 'azure_blob':
                return $this->testAzureBlob($storage);
            case 'local':
            default:
                return ['success' => true, 'message' => __('Local storage is always available', 'vendor-marketplace')];
        }
    }

    private function testS3Compatible(StorageConfigDTO $storage): array
    {
        if (!class_exists('Aws\S3\S3Client')) {
            return ['success' => false, 'message' => __('AWS SDK not installed', 'vendor-marketplace')];
        }

        try {
            $client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region'  => $storage->config['region'] ?? 'us-east-1',
                'endpoint' => $storage->config['endpoint'] ?? null,
                'credentials' => [
                    'key'    => $storage->config['access_key'] ?? '',
                    'secret' => $storage->config['secret_key'] ?? '',
                ],
            ]);

            $bucket = $storage->config['bucket'] ?? '';
            if (!$bucket) {
                return ['success' => false, 'message' => __('Bucket not configured', 'vendor-marketplace')];
            }

            $result = $client->headBucket(['Bucket' => $bucket]);

            return ['success' => true, 'message' => __('Connection successful', 'vendor-marketplace')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testGoogleCloud(StorageConfigDTO $storage): array
    {
        return ['success' => false, 'message' => __('Google Cloud Storage test not implemented', 'vendor-marketplace')];
    }

    private function testAzureBlob(StorageConfigDTO $storage): array
    {
        return ['success' => false, 'message' => __('Azure Blob Storage test not implemented', 'vendor-marketplace')];
    }
}
