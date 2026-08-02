<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;

class OCRStep implements WorkflowStepInterface
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
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::OCR);
        $this->jobs->updateProgress($jobId, 25);
        $this->jobs->appendLog($jobId, 'info', 'Starting OCR step');

        // If vision results include OCR text, reuse it
        $vision = $context->get('vision', []);
        $ocr = $vision['ocr'] ?? null;

        if ($ocr) {
            $context->set('ocr', $ocr);
            $this->jobs->appendLog($jobId, 'info', 'OCR extracted from vision provider');
            return $context;
        }

        // If not, attempt a light-weight OCR via analyzeImage with OCR flag (if provider supports it)
        try {
            if ($this->circuitBreaker->isOpen('vision')) {
                $this->jobs->appendLog($jobId, 'warning', 'Vision provider circuit open, skipping OCR');
                return $context;
            }

            $res = $this->orchestrator->analyzeImage((string) $context->get('image_url'), ['ocr' => true]);
            $this->circuitBreaker->recordSuccess($res['provider'] ?? 'vision');
            $ocrText = $res['ocr'] ?? null;
            $context->set('ocr', $ocrText);
            $this->jobs->appendLog($jobId, 'info', 'OCR completed');
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure('vision');
            $this->jobs->appendLog($jobId, 'warning', 'OCR failed: ' . $e->getMessage());
        }

        return $context;
    }
}
