<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

use VMP\Support\Config;

/**
 * Class AIConfiguration
 *
 * Description of administrative platform component AIConfiguration.
 *
 * @package vendor-marketplace
 */
class AIConfiguration
{
    private Config $config;

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        $this->config = Config::getInstance(defined('VMP_PLUGIN_DIR') ? VMP_PLUGIN_DIR . 'app/Config' : '');
    }

    /**
     * DefaultProvider functionality helper.
     *
     * @return string Output payload.
     */
    public function defaultProvider(): string
    {
        return (string) $this->get('ai.default_provider', 'unconfigured');
    }

    /**
     * ProviderFor functionality helper.
     *
     * @param string $capability Description index.
     * @return string Output payload.
     */
    public function providerFor(string $capability): string
    {
        return (string) $this->get("ai.providers.{$capability}", $this->defaultProvider());
    }

    /**
     * CacheEnabled functionality helper.
     *
     * @return bool Output payload.
     */
    public function cacheEnabled(): bool
    {
        return (bool) $this->get('ai.cache.enabled', true);
    }

    /**
     * CacheTtl functionality helper.
     *
     * @return int Output payload.
     */
    public function cacheTtl(): int
    {
        return (int) $this->get('ai.cache.ttl', 86400);
    }

    /**
     * RequiresHumanReview functionality helper.
     *
     * @return bool Output payload.
     */
    public function requiresHumanReview(): bool
    {
        return (bool) $this->get('ai.review.require_human_review', true);
    }

    /**
     * DefaultReviewStatus functionality helper.
     *
     * @return string Output payload.
     */
    public function defaultReviewStatus(): string
    {
        return (string) $this->get('ai.review.default_status', 'draft');
    }

    /**
     * MonthlyVendorCostLimit functionality helper.
     *
     * @return float Output payload.
     */
    public function monthlyVendorCostLimit(): float
    {
        return (float) $this->get('ai.limits.monthly_vendor_cost', 0.0);
    }

    /**
     * MonthlyVendorRequestLimit functionality helper.
     *
     * @return int Output payload.
     */
    public function monthlyVendorRequestLimit(): int
    {
        return (int) $this->get('ai.limits.monthly_vendor_requests', 0);
    }

    /**
     * Get functionality helper.
     *
     * @param string $key Description index.
     * @param mixed $default Description index.
     * @return mixed Output payload.
     */
    private function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default) ?? $default;
    }
}
