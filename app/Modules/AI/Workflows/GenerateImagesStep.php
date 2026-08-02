<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;

/**
 * Class GenerateImagesStep
 *
 * Generates product images using AI.
 *
 * @package vendor-marketplace
 */
class GenerateImagesStep implements WorkflowStepInterface
{
    private AIOrchestrator $orchestrator;
    private AIJobRepository $jobs;
    private RetryPolicy $retry;
    private CircuitBreaker $circuitBreaker;

    public function __construct(
        AIOrchestrator $orchestrator,
        AIJobRepository $jobs,
        RetryPolicy $retry,
        CircuitBreaker $circuitBreaker
    ) {
        $this->orchestrator = $orchestrator;
        $this->jobs = $jobs;
        $this->retry = $retry;
        $this->circuitBreaker = $circuitBreaker;
    }

    public function execute(WorkflowContext $context): WorkflowContext
    {
        $images = $this->orchestrator->generateImages($context->get('product_data', []));
        $context->set('generated_images', $images);
        return $context;
    }

    public function getName(): string
    {
        return 'generate_images';
    }
}
