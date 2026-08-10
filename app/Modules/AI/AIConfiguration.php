<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

use VMP\Support\Config;

/**
 * Class AIConfiguration
 *
 * AI configuration with DB override support.
 * Reads from app/Config/ai.php as defaults, then overlays vmp_ai_settings from database.
 *
 * @package vendor-marketplace
 */
class AIConfiguration
{
    private Config $config;
    private ?array $dbSettings = null;

    public function __construct()
    {
        $this->config = Config::getInstance(defined('VMP_PLUGIN_DIR') ? VMP_PLUGIN_DIR . 'app/Config' : '');
    }

    /**
     * Get database settings (cached per request).
     */
    private function getDbSettings(): array
    {
        if ($this->dbSettings === null) {
            $this->dbSettings = get_option('vmp_ai_settings', []);
        }
        return $this->dbSettings;
    }

    /**
     * Get a setting with DB override > static config > default.
     */
    private function getWithOverride(string $dbKey, string $configKey, mixed $default = null): mixed
    {
        $dbSettings = $this->getDbSettings();
        if (isset($dbSettings[$dbKey]) && $dbSettings[$dbKey] !== '') {
            return $dbSettings[$dbKey];
        }
        return $this->config->get($configKey, $default) ?? $default;
    }

    public function defaultProvider(): string
    {
        return (string) $this->getWithOverride('default_provider', 'ai.default_provider', 'unconfigured');
    }

    public function providerFor(string $capability): string
    {
        $dbKey = match ($capability) {
            'vision' => 'vision_provider',
            'text', 'llm' => 'llm_provider',
            'image_generation' => 'image_generation_provider',
            'search' => 'search_provider',
            default => $capability . '_provider',
        };
        return (string) $this->getWithOverride($dbKey, "ai.providers.{$capability}", $this->defaultProvider());
    }

    public function cacheEnabled(): bool
    {
        return (bool) $this->getWithOverride('cache_enabled', 'ai.cache.enabled', true);
    }

    public function cacheTtl(): int
    {
        return (int) $this->getWithOverride('cache_ttl', 'ai.cache.ttl', 86400);
    }

    public function requiresHumanReview(): bool
    {
        return (bool) $this->getWithOverride('require_human_review', 'ai.review.require_human_review', true);
    }

    public function defaultReviewStatus(): string
    {
        return (string) $this->getWithOverride('default_status', 'ai.review.default_status', 'draft');
    }

    public function monthlyVendorCostLimit(): float
    {
        return (float) $this->getWithOverride('monthly_vendor_cost_limit', 'ai.limits.monthly_vendor_cost', 0.0);
    }

    public function monthlyVendorRequestLimit(): int
    {
        return (int) $this->getWithOverride('monthly_vendor_request_limit', 'ai.limits.monthly_vendor_requests', 0);
    }

    /**
     * Get OpenAI-specific settings from DB.
     */
    public function openaiApiKey(): string
    {
        $dbSettings = $this->getDbSettings();
        $raw = $dbSettings['openai_api_key'] ?? '';
        if (empty($raw)) {
            return '';
        }
        // If encrypted, decrypt via SecretManager
        if (!empty($dbSettings['openai_api_key_encrypted'])) {
            try {
                $secretManager = \VMP\Core\Container::getInstance()->make(\VMP\Modules\AI\Security\SecretManager::class);
                $payload = json_decode(base64_decode($raw), true);
                if (is_array($payload) && isset($payload['ciphertext'], $payload['iv'], $payload['tag'])) {
                    return $secretManager->decryptSecret(
                        $payload['ciphertext'],
                        $payload['iv'],
                        $payload['tag']
                    );
                }
            } catch (\Throwable $e) {
                // Fall through to return empty
            }
        }
        return $raw;
    }


    public function openaiModel(): string
    {
        $dbSettings = $this->getDbSettings();
        return (string) ($dbSettings['openai_model'] ?? $this->config->get('ai.openai.model', 'gpt-4o'));
    }

    public function openaiVisionModel(): string
    {
        $dbSettings = $this->getDbSettings();
        return (string) ($dbSettings['openai_vision_model'] ?? $this->config->get('ai.openai.vision_model', 'gpt-4o'));
    }

}
