<?php
namespace VMP\Modules\AI\Jobs;

defined('ABSPATH') || exit;

use VMP\Core\Queue\JobInterface;
use VMP\Modules\AI\Services\AIProductDraftService;

class AIJobWorker implements JobInterface
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $jobId = sanitize_text_field($this->payload['job_id'] ?? '');
        $vendorId = (int) ($this->payload['vendor_id'] ?? 0);

        if ($jobId === '' || $vendorId <= 0) {
            throw new \RuntimeException('Invalid AI job payload.');
        }

        // 🔒 Atomic job locking — prevent duplicate processing
        $lock = new AIJobLock();
        if (!$lock->acquire($jobId, 300)) {
            throw new \RuntimeException(sprintf('Job %s is already being processed.', $jobId));
        }

        try {
            $this->processJob($jobId, $vendorId);
        } finally {
            // Always release the lock, even on failure
            $lock->release($jobId);
        }
    }

    /**
     * Process the AI job.
     */
    private function processJob(string $jobId, int $vendorId): void
    {
        $container = \VMP\Core\Container::getInstance();

        /** @var \VMP\Modules\AI\Workflows\WorkflowEngine $engine */
        $engine = $container->make(\VMP\Modules\AI\Workflows\WorkflowEngine::class);
        $workflow = new \VMP\Modules\AI\Workflows\ProductGenerationWorkflow($container);

        // build initial context from job
        $job = $container->make(\VMP\Modules\AI\Repositories\AIJobRepository::class)->find($jobId);
        $contextData = [
            'job_id' => $jobId,
            'vendor_id' => $vendorId,
            'image_url' => wp_get_attachment_url((int) ($job['attachment_id'] ?? 0)),
        ];

        $context = new \VMP\Modules\AI\Workflows\WorkflowContext($contextData);

        $repo = $container->make(\VMP\Modules\AI\Repositories\AIJobRepository::class);

        // Record worker started
        try {
            $repo->appendEvent($jobId, 'WorkerStarted', ['vendor_id' => $vendorId]);
        } catch (\Throwable $e) {
            // non-fatal
        }

        try {
            $resultContext = $engine->run($workflow, $context, ['cache' => false]);

            // update job result and mark as draft/review
            $repo->update($jobId, [
                'status' => \VMP\Modules\AI\States\AIProductWorkflowState::REVIEW,
                'current_step' => \VMP\Modules\AI\States\AIProductWorkflowState::REVIEW,
                'progress' => 100,
                'result' => $resultContext->all(),
            ]);

            // Record job completed
            try {
                $repo->appendEvent($jobId, 'JobCompleted', ['result_summary' => ['provider' => $resultContext->all()['provider'] ?? '']]);
            } catch (\Throwable $e) {
                // ignore
            }
        } catch (\Throwable $e) {
            $container->make(\VMP\Core\Logger::class)->error('AIWorker failed: ' . $e->getMessage());
            try {
                $repo->appendEvent($jobId, 'JobFailed', ['error' => $e->getMessage()]);
            } catch (\Throwable $_) {
            }
            $container->make(\VMP\Modules\AI\Repositories\AIJobRepository::class)->markFailed($jobId, $e->getMessage());
            throw $e;
        }
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }
}
