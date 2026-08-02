<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;

class SearchStep implements WorkflowStepInterface
{
    public function __construct(
        private AIOrchestrator $orchestrator,
        private AIJobRepository $jobs,
        private RetryPolicy $retry,
        private CircuitBreaker $circuitBreaker
    ) {
    }

    public function handle(WorkflowContext $context): WorkflowContext
    {
        $jobId = (string) $context->get('job_id');
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::SEARCHING);
        $this->jobs->updateProgress($jobId, 45);
        $this->jobs->appendLog($jobId, 'info', 'Starting external search');

        $query = (string) ($context->get('product.title') ?: $context->get('vision.labels.0') ?: $context->get('ocr'));

        try {
            if ($this->circuitBreaker->isOpen('search')) {
                $this->jobs->appendLog($jobId, 'warning', 'Search provider circuit open, skipping search');
                return $context;
            }

            $res = $this->orchestrator->search($query);
            $this->jobs->appendLog($jobId, 'info', 'Search completed', ['count' => count($res['results'] ?? [])]);
            $context->set('search_results', $res['results'] ?? []);
            $this->circuitBreaker->recordSuccess($res['provider'] ?? 'search');
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure('search');
            $this->jobs->appendLog($jobId, 'warning', 'Search failed: ' . $e->getMessage());
        }

        return $context;
    }
}
