<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;

/**
 * Class GenerateKeywordsStep
 *
 * Generates SEO keywords for products.
 *
 * @package vendor-marketplace
 */
class GenerateKeywordsStep implements WorkflowStepInterface
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
        $keywords = $this->orchestrator->generateKeywords($context->get('product_data', []));
        $context->set('keywords', $keywords);
        return $context;
    }

    public function getName(): string
    {
        return 'generate_keywords';
    }
}
