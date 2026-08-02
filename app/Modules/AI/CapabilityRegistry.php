<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

/**
 * Class CapabilityRegistry
 *
 * Responsible for declaring AI capability-to-provider order and default fallback rules.
 */
class CapabilityRegistry
{
    private array $defaultProviders = [
        'vision' => ['gemini', 'openai', 'openrouter'],
        'text' => ['gemini', 'openai', 'claude', 'openrouter'],
        'search' => ['openrouter', 'gemini', 'openai'],
        'image_generation' => ['openai', 'gemini'],
        'streaming' => ['gemini', 'openai', 'openrouter'],
    ];

    private array $configAliases = [
        'text' => 'llm',
        'streaming' => 'llm',
    ];

    public function __construct(private AIConfiguration $configuration)
    {
    }

    public function capabilities(): array
    {
        return array_keys($this->defaultProviders);
    }

    public function providersFor(string $capability): array
    {
        $capability = $this->normalizeCapability($capability);
        $configured = $this->configuration->providerFor($this->configAlias($capability));
        $order = $this->defaultProviders[$capability] ?? [];

        if ($configured === '' || $configured === 'unconfigured') {
            return $order;
        }

        return array_merge([$configured], array_values(array_diff($order, [$configured])));
    }

    public function defaultProviders(string $capability): array
    {
        return $this->defaultProviders[$this->normalizeCapability($capability)] ?? [];
    }

    public function configAlias(string $capability): string
    {
        return $this->configAliases[$this->normalizeCapability($capability)] ?? $this->normalizeCapability($capability);
    }

    private function normalizeCapability(string $capability): string
    {
        return strtolower(trim($capability));
    }
}
