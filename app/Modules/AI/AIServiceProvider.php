<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

use VMP\Contracts\AI\ImageGenerationProviderInterface;
use VMP\Contracts\AI\LLMProviderInterface;
use VMP\Contracts\AI\SearchProviderInterface;
use VMP\Contracts\AI\StreamingProviderInterface;
use VMP\Contracts\AI\TextProviderInterface;
use VMP\Contracts\AI\VisionProviderInterface;
use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Queue\QueueManager;
use VMP\Core\Queue\QueueInterface;
use VMP\Core\Queue\ActionSchedulerQueueAdapter;
use VMP\Core\Logger;
use VMP\Modules\AI\AIConfiguration;
use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\CapabilityRegistry;
use VMP\Modules\AI\Cost\CostTracker;
use VMP\Modules\AI\ProviderFailover;
use VMP\Modules\AI\ProviderHealth;
use VMP\Modules\AI\Pipelines\ProductGenerationPipeline;
use VMP\Modules\AI\Controllers\AIProductController;
use VMP\Modules\AI\ProviderResolver;
use VMP\Modules\AI\Providers\UnconfiguredImageGenerationProvider;
use VMP\Modules\AI\Providers\UnconfiguredLLMProvider;
use VMP\Modules\AI\Providers\UnconfiguredSearchProvider;
use VMP\Modules\AI\Providers\UnconfiguredStreamingProvider;
use VMP\Modules\AI\Providers\UnconfiguredVisionProvider;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\Services\AIProductDraftService;
use VMP\Providers\ServiceProvider;
use VMP\Modules\AI\CircuitBreaker;
use VMP\Modules\AI\ProviderHealthScore;
use VMP\Modules\AI\RetryPolicy;
use VMP\Support\CacheManager;
use VMP\Services\SubscriptionService;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AIConfiguration::class, static fn(): AIConfiguration => new AIConfiguration());
        $this->container->singleton(CostTracker::class, static fn(): CostTracker => new CostTracker());
        $this->container->singleton(AIJobRepository::class, static fn(): AIJobRepository => new AIJobRepository($GLOBALS['wpdb']));

        // Encryption & Secret management
        $this->container->singleton(\VMP\Modules\AI\Security\KeyManager::class, function () {
            return new \VMP\Modules\AI\Security\KeyManager('VMP_ENCRYPTION_KEY');
        });

        $this->container->singleton(\VMP\Modules\AI\Security\EncryptionService::class, function () {
            return new \VMP\Modules\AI\Security\EncryptionService('aes-256-gcm');
        });

        $this->container->singleton(\VMP\Modules\AI\Security\SecretManager::class, function () {
            return new \VMP\Modules\AI\Security\SecretManager(
                $this->container->make(\VMP\Modules\AI\Security\EncryptionService::class),
                $this->container->make(\VMP\Modules\AI\Security\KeyManager::class)
            );
        });

        $this->container->singleton(\VMP\Modules\AI\Repositories\ProviderSecretRepository::class, function () {
            return new \VMP\Modules\AI\Repositories\ProviderSecretRepository($GLOBALS['wpdb'], $this->container->make(\VMP\Modules\AI\Security\SecretManager::class));
        });

        // Queue adapter (Action Scheduler preferred, fallback to DB QueueManager)
        $this->container->singleton(QueueInterface::class, function () {
            return new ActionSchedulerQueueAdapter(
                $this->container->make(Logger::class),
                $this->container->make(QueueManager::class)
            );
        });

        // Infrastructure helpers
        $this->container->singleton(\VMP\Modules\AI\ProviderMonitor::class, function () {
            return new \VMP\Modules\AI\ProviderMonitor();
        });

        $this->container->singleton(CapabilityRegistry::class, fn(): CapabilityRegistry => new CapabilityRegistry(
            $this->container->make(AIConfiguration::class)
        ));

        $this->container->singleton(ProviderHealth::class, fn(): ProviderHealth => new ProviderHealth());

        $this->container->singleton(CacheManager::class, fn(): CacheManager => CacheManager::getInstance());
        $this->container->singleton(RetryPolicy::class, fn(): RetryPolicy => new RetryPolicy());

        $this->container->singleton(CircuitBreaker::class, fn(): CircuitBreaker => new CircuitBreaker(
            $this->container->make(CacheManager::class)
        ));

        $this->container->singleton(ProviderHealthScore::class, fn(): ProviderHealthScore => new ProviderHealthScore(
            $this->container->make(CacheManager::class)
        ));

        $this->container->singleton(ProviderFailover::class, fn(): ProviderFailover => new ProviderFailover(
            $this->container->make(CapabilityRegistry::class),
            $this->container->make(ProviderHealth::class),
            $this->container->make(CircuitBreaker::class),
            $this->container->make(ProviderHealthScore::class),
            $this->container->make(RetryPolicy::class)
        ));

        $this->container->singleton(VisionProviderInterface::class, static fn(): VisionProviderInterface => new UnconfiguredVisionProvider());
        $this->container->singleton(SearchProviderInterface::class, static fn(): SearchProviderInterface => new UnconfiguredSearchProvider());
        $this->container->singleton(LLMProviderInterface::class, static fn(): LLMProviderInterface => new UnconfiguredLLMProvider());
        $this->container->singleton(ImageGenerationProviderInterface::class, static fn(): ImageGenerationProviderInterface => new UnconfiguredImageGenerationProvider());
        $this->container->singleton(StreamingProviderInterface::class, static fn(): StreamingProviderInterface => new UnconfiguredStreamingProvider());

        $this->container->singleton(ProviderResolver::class, fn(): ProviderResolver => new ProviderResolver(
            $this->container,
            $this->container->make(AIConfiguration::class),
            $this->container->make(CapabilityRegistry::class),
            $this->container->make(ProviderFailover::class),
            $this->container->make(ProviderHealth::class)
        ));
        $this->container->singleton(AIOrchestrator::class, fn(): AIOrchestrator => new AIOrchestrator(
            $this->container->make(ProviderResolver::class)
        ));

        // Workflow engine
        $this->container->singleton(\VMP\Modules\AI\Workflows\WorkflowEngine::class, fn(): \VMP\Modules\AI\Workflows\WorkflowEngine => new \VMP\Modules\AI\Workflows\WorkflowEngine(
            $this->container->make(\VMP\Modules\AI\Cache\AICache::class),
            $this->container->make(\VMP\Modules\AI\AIConfiguration::class)
        ));

        $this->container->singleton(ProductGenerationPipeline::class, fn(): ProductGenerationPipeline => new ProductGenerationPipeline(
            $this->container->make(AIOrchestrator::class),
            $this->container->make(CostTracker::class),
            $this->container->make(AIConfiguration::class)
        ));
        $this->container->singleton(AIProductDraftService::class, fn(): AIProductDraftService => new AIProductDraftService(
            $this->container->make(ProductGenerationPipeline::class),
            $this->container->make(ProductRepositoryInterface::class),
            $this->container->make(VendorRepositoryInterface::class),
            $this->container->make(AIJobRepository::class),
            $this->container->make(QueueInterface::class)
        ));
        $this->container->singleton(AIProductController::class, fn(): AIProductController => new AIProductController(
            $this->container->make(VendorRepositoryInterface::class),
            $this->container->make(AIProductDraftService::class),
            $this->container->make(SubscriptionService::class)
        ));
    }

    public function boot(): void
    {
        add_action('wp_ajax_vmp_ai_create_product_from_image', [$this->controller(), 'createJob']);
        add_action('wp_ajax_vmp_ai_get_product_job', [$this->controller(), 'getJob']);
        add_action('wp_ajax_vmp_ai_get_job_timeline', [$this->controller(), 'getJobTimeline']);
        add_action('wp_ajax_vmp_ai_regenerate_product_part', [$this->controller(), 'regenerate']);
        add_action('wp_ajax_vmp_ai_publish_product_draft', [$this->controller(), 'publish']);

        // Admin notice if encryption key not configured
        add_action('admin_notices', [$this, 'encryptionKeyAdminNotice']);
    }

    /**
     * Show an admin notice if VMP_ENCRYPTION_KEY is not defined or invalid
     */
    public function encryptionKeyAdminNotice(): void
    {
        if ( !current_user_can('manage_options') ) {
            return;
        }

        if ( defined('VMP_ENCRYPTION_KEY') && !empty(constant('VMP_ENCRYPTION_KEY')) ) {
            return;
        }

        $class = 'notice notice-error';
        $message = __('VMP Encryption key is not configured. Define VMP_ENCRYPTION_KEY in wp-config.php to enable encrypted storage of provider secrets.', 'vendor-marketplace');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    private function controller(): AIProductController
    {
        return $this->container->make(AIProductController::class);
    }
}
