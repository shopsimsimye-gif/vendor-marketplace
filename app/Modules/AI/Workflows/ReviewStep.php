<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\Repositories\AIJobRepository;

class ReviewStep implements WorkflowStepInterface
{
    public function __construct(private AIJobRepository $jobs)
    {
    }

    public function handle(WorkflowContext $context): WorkflowContext
    {
        $jobId = (string) $context->get('job_id');
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::REVIEW);
        $this->jobs->updateProgress($jobId, 98);
        $this->jobs->appendLog($jobId, 'info', 'Workflow reached REVIEW state');

        // prepare final result structure
        $result = [
            'title' => $context->get('title', ''),
            'description' => $context->get('description', ''),
            'short_description' => isset($context->get('description')[0]) ? substr($context->get('description'), 0, 180) : '',
            'keywords' => $context->get('keywords', []),
            'specifications' => $context->get('merged.specs', []),
            'provider' => $context->get('provider') ?? '',
            'confidence' => $context->get('vision')['confidence'] ?? 0.0,
            'warnings' => [],
            'sources' => $context->get('search_results', []),
            'metadata' => $context->all(),
        ];

        $this->jobs->update($jobId, ['result' => $result]);

        return $context;
    }
}
