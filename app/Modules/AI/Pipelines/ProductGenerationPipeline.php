<?php
namespace VMP\Modules\AI\Pipelines;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\AIConfiguration;
use VMP\Modules\AI\Cost\CostTracker;
use VMP\Modules\AI\Context\ImageContext;
use VMP\Modules\AI\Context\ProductContext;
use VMP\Modules\AI\Context\StoreContext;
use VMP\Modules\AI\Context\VendorContext;
use VMP\Modules\AI\Prompts\GenerateDescriptionPrompt;
use VMP\Modules\AI\Prompts\GenerateSEOKeywordsPrompt;
use VMP\Modules\AI\Prompts\GenerateTitlePrompt;
use VMP\Modules\AI\Results\AIResult;

/**
 * Class ProductGenerationPipeline
 *
 * Description of administrative platform component ProductGenerationPipeline.
 *
 * @package vendor-marketplace
 */
class ProductGenerationPipeline
{
    public function __construct(
        private AIOrchestrator $ai,
        private CostTracker $costTracker,
        private AIConfiguration $configuration
    ) {
    }

    public function run(
        ImageContext $image,
        ?ProductContext $product = null,
        ?VendorContext $vendor = null,
        ?StoreContext $store = null,
        array $options = []
    ): AIResult {
        $this->costTracker->reset();

        $context = $this->mergeContext($image, $product, $vendor, $store);
        $vision = $this->ai->analyzeImage($image->image, $options['vision'] ?? []);
        $this->costTracker->fromProviderResponse('vision', $vision);
        $context['vision'] = $vision;

        $search = $this->ai->search($this->buildSearchQuery($context), $options['search'] ?? []);
        $this->costTracker->fromProviderResponse('search', $search);
        $context['search'] = $search;

        $title = $this->ai->generateTitle((new GenerateTitlePrompt())->messages($context), $options['title'] ?? []);
        $this->costTracker->fromProviderResponse('llm.title', $title);
        $description = $this->ai->generateDescription((new GenerateDescriptionPrompt())->messages($context), $options['description'] ?? []);
        $this->costTracker->fromProviderResponse('llm.description', $description);
        $keywords = $this->ai->generateSEOKeywords((new GenerateSEOKeywordsPrompt())->messages($context), $options['keywords'] ?? []);
        $this->costTracker->fromProviderResponse('llm.keywords', $keywords);
        $usage = $this->costTracker->summary();

        $descriptionText = (string) ($description['description'] ?? $description['content'] ?? '');

        return AIResult::fromArray([
            'title' => (string) ($title['title'] ?? $title['content'] ?? ''),
            'description' => $descriptionText,
            'short_description' => $this->shortDescription($descriptionText),
            'keywords' => is_array($keywords['keywords'] ?? null) ? $keywords['keywords'] : [],
            'specifications' => is_array($vision['attributes'] ?? null) ? $vision['attributes'] : [],
            'confidence' => (float) ($vision['confidence'] ?? 0.0),
            'warnings' => array_values(array_filter(array_merge(
                $this->arrayValue($vision['warnings'] ?? []),
                $this->arrayValue($title['warnings'] ?? []),
                $this->arrayValue($description['warnings'] ?? [])
            ))),
            'provider' => (string) ($title['provider'] ?? $vision['provider'] ?? ''),
            'latency_ms' => (int) $usage['latency_ms'],
            'tokens' => (int) $usage['tokens'],
            'cost' => (float) $usage['cost'],
            'sources' => is_array($search['results'] ?? null) ? $search['results'] : [],
            'status' => $this->configuration->defaultReviewStatus(),
            'review_status' => $this->configuration->requiresHumanReview() ? 'pending_review' : 'approved',
            'metadata' => [
                'vision' => $vision,
                'title' => $title,
                'description' => $description,
                'keywords' => $keywords,
                'usage' => $usage,
            ],
        ]);
    }

    private function mergeContext(
        ImageContext $image,
        ?ProductContext $product,
        ?VendorContext $vendor,
        ?StoreContext $store
    ): array {
        return array_replace_recursive(
            $image->toPromptContext(),
            $product?->toPromptContext() ?? [],
            $vendor?->toPromptContext() ?? [],
            $store?->toPromptContext() ?? []
        );
    }

    /**
     * BuildSearchQuery functionality helper.
     *
     * @param array $context Description index.
     * @return string Output payload.
     */
    private function buildSearchQuery(array $context): string
    {
        $title = (string) ($context['product']['title'] ?? '');
        $labels = $context['vision']['labels'] ?? [];

        if ($title !== '') {
            return $title;
        }

        if (is_array($labels) && $labels !== []) {
            return implode(' ', array_slice($labels, 0, 5));
        }

        return 'product details';
    }

    /**
     * ArrayValue functionality helper.
     *
     * @param mixed $value Description index.
     * @return array Output payload.
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function shortDescription(string $description): string
    {
        $description = trim(wp_strip_all_tags($description));
        if ($description === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($description, 0, 180);
        }

        return substr($description, 0, 180);
    }
}
