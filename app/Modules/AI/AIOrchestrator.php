<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

use VMP\Modules\AI\Context\CapabilityContext;

/**
 * Class AIOrchestrator
 *
 * Description of administrative platform component AIOrchestrator.
 *
 * @package vendor-marketplace
 */
class AIOrchestrator
{
    /**
     *   Construct functionality helper.
     *
     * @param ProviderResolver $providers Description index.
     * @return void Output payload.
     */
    public function __construct(private ProviderResolver $providers)
    {
    }

    /**
     * AnalyzeImage functionality helper.
     *
     * @param string $image Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function analyzeImage(string $image, array $options = []): array
    {
        return $this->providers->vision()->analyze($image, $options);
    }

    /**
     * Search functionality helper.
     *
     * @param string $query Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function search(string $query, array $options = []): array
    {
        return $this->providers->search()->search($query, $options);
    }

    /**
     * GenerateTitle functionality helper.
     *
     * @param array $messages Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function generateTitle(array $messages, array $options = []): array
    {
        return $this->providers->llm()->generate(CapabilityContext::text($messages, $options + ['task' => 'generate_title']));
    }

    /**
     * GenerateDescription functionality helper.
     *
     * @param array $messages Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function generateDescription(array $messages, array $options = []): array
    {
        return $this->providers->llm()->generate(CapabilityContext::text($messages, $options + ['task' => 'generate_description']));
    }

    /**
     * GenerateSEOKeywords functionality helper.
     *
     * @param array $messages Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function generateSEOKeywords(array $messages, array $options = []): array
    {
        return $this->providers->llm()->generate(CapabilityContext::text($messages, $options + ['task' => 'generate_seo_keywords']));
    }

    /**
     * GenerateAdvertisement functionality helper.
     *
     * @param array $messages Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function generateAdvertisement(array $messages, array $options = []): array
    {
        return $this->providers->llm()->generate(CapabilityContext::text($messages, $options + ['task' => 'generate_advertisement']));
    }

    /**
     * GenerateImage functionality helper.
     *
     * @param string $prompt Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function generateImage(string $prompt, array $options = []): array
    {
        return $this->providers->imageGeneration()->generate($prompt, $options);
    }
}
