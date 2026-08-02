<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;

class ProductGenerationWorkflow implements WorkflowInterface
{
    public function __construct(private Container $container)
    {
    }

    public function name(): string
    {
        return 'product-image-v1';
    }

    public function steps(): array
    {
        $container = $this->container;
        $orchestrator = $container->make(AIOrchestrator::class);
        $jobs = $container->make(AIJobRepository::class);
        $retry = $container->make(RetryPolicy::class);
        $cb = $container->make(CircuitBreaker::class);

        return [
            new AnalyzeImageStep($orchestrator, $jobs, $retry, $cb),
            new OCRStep($orchestrator, $jobs, $retry, $cb),
            new BarcodeStep($orchestrator, $jobs, $retry, $cb),
            new SearchStep($orchestrator, $jobs, $retry, $cb),
            new MergeStep($orchestrator, $jobs, $retry, $cb),
            new GenerateTitleStep($orchestrator, $jobs, $retry, $cb),
            new GenerateDescriptionStep($orchestrator, $jobs, $retry, $cb),
            new GenerateSEOStep($orchestrator, $jobs, $retry, $cb),
            new GenerateKeywordsStep($orchestrator, $jobs, $retry, $cb),
            new GenerateAttributesStep($orchestrator, $jobs, $retry, $cb),
            new GenerateImagesStep($orchestrator, $jobs, $retry, $cb),
            new ReviewStep($orchestrator, $jobs, $retry, $cb),
            new DraftStep($orchestrator, $jobs, $retry, $cb),
        ];
    }
}
