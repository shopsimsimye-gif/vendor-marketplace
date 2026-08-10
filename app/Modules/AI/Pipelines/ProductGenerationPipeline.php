<?php
namespace VMP\Modules\AI\Pipelines;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\AIConfiguration;
use VMP\Modules\AI\Cost\CostTracker;
use VMP\Modules\AI\Repositories\AIUsageLedgerRepository;
use VMP\Modules\AI\Context\ImageContext;
use VMP\Modules\AI\Context\ProductContext;
use VMP\Modules\AI\Context\StoreContext;
use VMP\Modules\AI\Context\VendorContext;
use VMP\Modules\AI\Prompts\GenerateDescriptionPrompt;
use VMP\Modules\AI\Prompts\GenerateSEOKeywordsPrompt;
use VMP\Modules\AI\Prompts\GenerateTitlePrompt;
use VMP\Modules\AI\Results\AIResult;
use VMP\Modules\AI\Exceptions\RetryLaterException;

/**
 * Class ProductGenerationPipeline
 *
 * AI product generation pipeline with vendor usage limit enforcement.
 *
 * @package vendor-marketplace
 */
class ProductGenerationPipeline
{
    public function __construct(
        private AIOrchestrator $ai,
        private CostTracker $costTracker,
        private AIConfiguration $configuration,
        private AIUsageLedgerRepository $usageLedger
    ) {
    }

    public function run(
        ImageContext $image,
        ?ProductContext $product = null,
        ?VendorContext $vendor = null,
        ?StoreContext $store = null,
        array $options = []
    ): AIResult {
        // ── Enforce vendor usage limits before starting ──
        if ($vendor !== null && $vendor->id > 0) {
            $this->enforceVendorLimits($vendor->id);
        }

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

        // ── Persist usage to ledger ──
        if ($vendor !== null && $vendor->id > 0) {
            $this->recordUsage($vendor->id, $usage, $options['job_id'] ?? '');
        }

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

    /**
     * Enforce monthly vendor cost and request limits.
     * Throws RetryLaterException if limit exceeded.
     */
    private function enforceVendorLimits(int $vendorId): void
    {
        $costLimit = $this->configuration->monthlyVendorCostLimit();
        $requestLimit = $this->configuration->monthlyVendorRequestLimit();

        if ($costLimit > 0) {
            $currentCost = $this->usageLedger->getMonthlyCost($vendorId);
            if ($currentCost >= $costLimit) {
                throw new RetryLaterException(
                    sprintf(
                        'تم تجاوز الحد الأقصى للتكلفة الشهرية (%.2f). التكلفة الحالية: %.2f',
                        $costLimit,
                        $currentCost
                    ),
                    429,
                    3600 // retry after 1 hour
                );
            }
        }

        if ($requestLimit > 0) {
            $currentRequests = $this->usageLedger->getMonthlyRequestCount($vendorId);
            if ($currentRequests >= $requestLimit) {
                throw new RetryLaterException(
                    sprintf(
                        'تم تجاوز الحد الأقصى لعدد الطلبات الشهرية (%d). الطلبات الحالية: %d',
                        $requestLimit,
                        $currentRequests
                    ),
                    429,
                    3600
                );
            }
        }
    }

    /**
     * Record usage to persistent ledger.
     */
    private function recordUsage(int $vendorId, array $usage, string $jobId = ''): void
    {
        // The CostTracker summary has aggregated data; we need per-call data.
        // For now, record one aggregate entry per pipeline run.
        // In future, could record per-step (vision, search, llm) separately.
        $this->usageLedger->record([
            'vendor_id'     => $vendorId,
            'job_id'        => $jobId,
            'provider'      => $usage['providers'] ? array_key_first($usage['providers']) : '',
            'capability'    => 'product_generation',
            'input_tokens'  => 0, // not tracked in summary
            'output_tokens' => 0,
            'images'        => $usage['images'] ?? 0,
            'searches'      => $usage['searches'] ?? 0,
            'cost'          => $usage['cost'] ?? 0.0,
            'latency_ms'    => $usage['latency_ms'] ?? 0,
            'metadata'      => [
                'providers' => $usage['providers'] ?? [],
                'requests'  => $usage['requests'] ?? 0,
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
