<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;
use VMP\Modules\AI\Prompts\GenerateSEOKeywordsPrompt;

class GenerateSEOStep implements WorkflowStepInterface
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
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::GENERATING_SEO);
        $this->jobs->updateProgress($jobId, 88);
        $this->jobs->appendLog($jobId, 'info', 'Generating SEO keywords');

        $prompt = new GenerateSEOKeywordsPrompt();
        $messages = $prompt->messages($context->all());

        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                if ($this->circuitBreaker->isOpen('llm')) {
                    $this->jobs->appendLog($jobId, 'warning', 'LLM provider circuit open, skipping SEO generation');
                    break;
                }

                $res = $this->orchestrator->generateSEOKeywords($messages);
                $this->jobs->appendLog($jobId, 'info', 'SEO keywords generated', ['provider' => $res['provider'] ?? null]);
                $this->circuitBreaker->recordSuccess($res['provider'] ?? 'llm');
                $context->set('keywords', $res['keywords'] ?? []);
                break;
            } catch (\Throwable $e) {
                $this->circuitBreaker->recordFailure('llm');
                $this->jobs->appendLog($jobId, 'warning', 'SEO generation failed: ' . $e->getMessage());
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
