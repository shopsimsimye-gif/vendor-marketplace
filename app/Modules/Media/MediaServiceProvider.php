<?php

declare(strict_types=1);

namespace VMP\Modules\Media;

defined('ABSPATH') || exit;

use VMP\Providers\ServiceProvider;
use VMP\Modules\Media\Repositories\MediaRepository;
use VMP\Modules\Media\Repositories\FolderRepository;
use VMP\Modules\Media\Repositories\StorageRepository;
use VMP\Modules\Media\Repositories\CachedMediaRepository;
use VMP\Modules\Media\Repositories\CachedFolderRepository;
use VMP\Modules\Media\Repositories\CachedStorageRepository;
use VMP\Modules\Media\Contracts\MediaRepositoryInterface;
use VMP\Modules\Media\Contracts\FolderRepositoryInterface;
use VMP\Modules\Media\Contracts\StorageRepositoryInterface;
use VMP\Modules\Media\Services\MediaService;
use VMP\Modules\Media\Services\StorageService;
use VMP\Modules\Media\Services\FolderService;
use VMP\Modules\Media\Policies\MediaPolicy;
use VMP\Modules\Media\Http\Controllers\Api\MediaApiController;
use VMP\Core\EventManager;
use VMP\Core\Logger;
use VMP\Infrastructure\Dispatcher\RouteRegistry;
use VMP\Core\Container;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerServices();
        $this->registerPolicy();
        $this->registerController();
    }

    protected function registerRepositories(): void
    {
        $this->container->singleton(MediaRepository::class, static fn(): MediaRepository => new MediaRepository());
        $this->container->singleton(MediaRepositoryInterface::class, fn() => new CachedMediaRepository($this->container->make(MediaRepository::class)));

        $this->container->singleton(FolderRepository::class, static fn(): FolderRepository => new FolderRepository());
        $this->container->singleton(FolderRepositoryInterface::class, fn() => new CachedFolderRepository($this->container->make(FolderRepository::class)));

        $this->container->singleton(StorageRepository::class, static fn(): StorageRepository => new StorageRepository());
        $this->container->singleton(StorageRepositoryInterface::class, fn() => new CachedStorageRepository($this->container->make(StorageRepository::class)));
    }

    protected function registerServices(): void
    {
        $this->container->singleton(MediaService::class, function () {
            return new MediaService(
                $this->container->make(MediaRepositoryInterface::class),
                $this->container->make(FolderRepositoryInterface::class),
                $this->container->make(StorageRepositoryInterface::class),
                $this->container->make(EventManager::class),
                $this->container->make(Logger::class)
            );
        });

        $this->container->singleton(StorageService::class, function () {
            return new StorageService(
                $this->container->make(StorageRepositoryInterface::class),
                $this->container->make(MediaRepositoryInterface::class),
                $this->container->make(Logger::class)
            );
        });

        $this->container->singleton(FolderService::class, function () {
            return new FolderService(
                $this->container->make(FolderRepositoryInterface::class),
                $this->container->make(MediaRepositoryInterface::class),
                $this->container->make(EventManager::class),
                $this->container->make(Logger::class)
            );
        });
    }

    protected function registerPolicy(): void
    {
        $this->container->singleton(MediaPolicy::class, static fn(): MediaPolicy => new MediaPolicy());
    }

    protected function registerController(): void
    {
        $this->container->singleton(MediaApiController::class, function () {
            return new MediaApiController(
                $this->container->make(MediaService::class),
                $this->container->make(StorageService::class),
                $this->container->make(FolderService::class),
                $this->container->make(MediaPolicy::class)
            );
        });
    }

    public function boot(): void
    {
        // Register REST API routes
        /** @var MediaApiController $controller */
        $controller = $this->container->make(MediaApiController::class);
        
        // Use global container instance directly for RouteRegistry
        $globalContainer = Container::getInstance();
        $registry = $globalContainer->make(RouteRegistry::class);
        
        if ($registry === null) {
            error_log('[VMP][Media] ERROR: RouteRegistry still null from global container');
            // Create a new one as fallback
            $registry = new RouteRegistry();
        }
        
        $controller->registerRoutes($registry);

        // Run migrations
        $this->runMigrations();
    }

    /**
     * Run media tables migration.
     */
    private function runMigrations(): void
    {
        $migrationFile = VMP_PLUGIN_DIR . 'app/Database/Migrations/009_create_vmp_media_tables.php';
        if (is_file($migrationFile)) {
            require_once $migrationFile;
            \VMP\Database\Migrations\CreateVmpMediaTables::up();
        }
    }
}
