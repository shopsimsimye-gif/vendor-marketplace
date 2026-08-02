<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

use VMP\Contracts\AI\ImageGenerationProviderInterface;
use VMP\Contracts\AI\LLMProviderInterface;
use VMP\Contracts\AI\SearchProviderInterface;
use VMP\Contracts\AI\StreamingProviderInterface;
use VMP\Contracts\AI\TextProviderInterface;
use VMP\Contracts\AI\VisionProviderInterface;
use VMP\Core\Container;
use VMP\Modules\AI\ProviderFailover;
use VMP\Modules\AI\ProviderHealth;
use VMP\Modules\AI\CapabilityRegistry;

/**
 * Class ProviderResolver
 *
 * Description of administrative platform component ProviderResolver.
 *
 * @package vendor-marketplace
 */
class ProviderResolver
{
    public function __construct(
        private Container $container,
        private AIConfiguration $configuration,
        private CapabilityRegistry $registry,
        private ProviderFailover $failover,
        private ProviderHealth $health
    ) {
    }

    /**
     * Vision functionality helper.
     *
     * @return VisionProviderInterface Output payload.
     */
    public function vision(): VisionProviderInterface
    {
        return $this->resolve(VisionProviderInterface::class, 'vision');
    }

    /**
     * Llm functionality helper.
     *
     * @return LLMProviderInterface Output payload.
     */
    public function llm(): LLMProviderInterface
    {
        return $this->resolve(LLMProviderInterface::class, 'text');
    }

    /**
     * ImageGeneration functionality helper.
     *
     * @return ImageGenerationProviderInterface Output payload.
     */
    public function imageGeneration(): ImageGenerationProviderInterface
    {
        return $this->resolve(ImageGenerationProviderInterface::class, 'image_generation');
    }

    /**
     * Search functionality helper.
     *
     * @return SearchProviderInterface Output payload.
     */
    public function search(): SearchProviderInterface
    {
        return $this->resolve(SearchProviderInterface::class, 'search');
    }

    /**
     * Streaming functionality helper.
     *
     * @return StreamingProviderInterface Output payload.
     */
    public function streaming(): StreamingProviderInterface
    {
        return $this->resolve(StreamingProviderInterface::class, 'streaming');
    }

    /**
     * Resolve functionality helper.
     *
     * @param string $contract Description index.
     * @param string $capability Description index.
     * @return mixed Output payload.
     */
    private function resolve(string $contract, string $capability): mixed
    {
        $capability = strtolower(trim($capability));
        $configuredProvider = $this->configuration->providerFor($this->registry->configAlias($capability));
        $resolvedProvider = $configuredProvider;

        if ($configuredProvider === '' || $configuredProvider === 'unconfigured' || !$this->health->isHealthy($configuredProvider)) {
            $resolvedProvider = $this->failover->resolve($capability);
        }

        $providerBinding = $contract . ':' . $resolvedProvider;
        if ($resolvedProvider !== '' && $this->container->has($providerBinding)) {
            return $this->container->make($providerBinding);
        }

        return $this->container->make($contract);
    }
}
