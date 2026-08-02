<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;

class AnalyzeImageStep implements WorkflowStepInterface
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
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::ANALYZING_IMAGE);
        $this->jobs->updateProgress($jobId, 15);
        $this->jobs->appendLog($jobId, 'info', 'Starting image analysis');

        $image = (string) $context->get('image_url');

        $attempt = 0;
        $result = [];
        while (true) {
            $attempt++;
            try {
                if ($this->circuitBreaker->isOpen('vision')) {
                    $this->jobs->appendLog($jobId, 'warning', 'Vision provider circuit open, skipping vision step');
                    break;
                }

                $result = $this->orchestrator->analyzeImage($image);

                // record provider monitor success
                $this->circuitBreaker->recordSuccess($result['provider'] ?? 'vision');

                $this->jobs->appendLog($jobId, 'info', 'Image analysis completed', ['provider' => $result['provider'] ?? null]);
                break;
            } catch (\Throwable $e) {
                $this->circuitBreaker->recordFailure('vision');
                $this->jobs->appendLog($jobId, 'warning', 'Image analysis failed: ' . $e->getMessage());
                if (!$this->retry->shouldRetry($attempt)) {
                    $this->jobs->markFailed($jobId, $e->getMessage());
                    throw $e;
                }
                sleep($this->retry->nextDelay($attempt));
            }
        }

        $context->set('vision', $result);
        return $context;
    }
}
