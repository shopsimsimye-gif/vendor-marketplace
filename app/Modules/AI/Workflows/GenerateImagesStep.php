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
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::GENERATING_IMAGES);
        $this->jobs->updateProgress($jobId, 95);
        $this->jobs->appendLog($jobId, 'info', 'Generating product images');

        $title = (string) $context->get('title');
        $description = (string) $context->get('description');
        $prompt = "Product photography of: {$title}. {$description}. Professional, clean background, high quality.";

        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                if ($this->circuitBreaker->isOpen('image_generation')) {
                    $this->jobs->appendLog($jobId, 'warning', 'Image generation provider circuit open, skipping');
                    break;
                }

                $res = $this->orchestrator->generateImage($prompt, ['quality' => 'hd']);
                $this->jobs->appendLog($jobId, 'info', 'Images generated', ['provider' => $res['provider'] ?? null, 'count' => count($res['images'] ?? [])]);
                $this->circuitBreaker->recordSuccess($res['provider'] ?? 'image_generation');
                $context->set('generated_images', $res['images'] ?? []);
                break;
            } catch (\Throwable $e) {
                $this->circuitBreaker->recordFailure('image_generation');
                $this->jobs->appendLog($jobId, 'warning', 'Image generation failed: ' . $e->getMessage());
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
