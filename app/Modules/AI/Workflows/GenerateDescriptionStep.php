<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;
use VMP\Modules\AI\Prompts\GenerateDescriptionPrompt;

class GenerateDescriptionStep implements WorkflowStepInterface
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
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::GENERATING_DESCRIPTION);
        $this->jobs->updateProgress($jobId, 75);
        $this->jobs->appendLog($jobId, 'info', 'Generating description');

        $prompt = new GenerateDescriptionPrompt();
        $messages = $prompt->messages($context->all());

        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                if ($this->circuitBreaker->isOpen('llm')) {
                    $this->jobs->appendLog($jobId, 'warning', 'LLM provider circuit open, skipping description generation');
                    break;
                }

                $res = $this->orchestrator->generateDescription($messages);
                $this->jobs->appendLog($jobId, 'info', 'Description generated', ['provider' => $res['provider'] ?? null]);
                $this->circuitBreaker->recordSuccess($res['provider'] ?? 'llm');
                $context->set('description', $res['description'] ?? ($res['content'] ?? ''));
                break;
            } catch (\Throwable $e) {
                $this->circuitBreaker->recordFailure('llm');
                $this->jobs->appendLog($jobId, 'warning', 'Description generation failed: ' . $e->getMessage());
                if (!$this->retry->shouldRetry($attempt)) {
                    $this->jobs->markFailed($jobId, $e->getMessage());
                    throw $e;
                }
                sleep($this->retry->nextDelay($attempt));
            }
        }

        return $context;
    }
}
