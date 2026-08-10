<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIOrchestrator;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\RetryPolicy;
use VMP\Modules\AI\CircuitBreaker;

/**
 * Class BarcodeStep
 *
 * Extracts barcode information from product images.
 *
 * @package vendor-marketplace
 */
class BarcodeStep implements WorkflowStepInterface
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
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::BARCODE);
        $this->jobs->updateProgress($jobId, 35);
        $this->jobs->appendLog($jobId, 'info', 'Starting barcode extraction');

        try {
            if ($this->circuitBreaker->isOpen('vision')) {
                $this->jobs->appendLog($jobId, 'warning', 'Vision provider circuit open, skipping barcode');
                return $context;
            }

            $res = $this->orchestrator->analyzeImage((string) $context->get('image_url'), ['barcode' => true]);
            $this->circuitBreaker->recordSuccess($res['provider'] ?? 'vision');
            $context->set('barcode', $res['barcode'] ?? null);
            $this->jobs->appendLog($jobId, 'info', 'Barcode extraction completed');
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure('vision');
            $this->jobs->appendLog($jobId, 'warning', 'Barcode extraction failed: ' . $e->getMessage());
        }

        return $context;
    }
}
