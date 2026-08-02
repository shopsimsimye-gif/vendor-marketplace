<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\Repositories\AIJobRepository;

class MergeStep implements WorkflowStepInterface
{
    public function __construct(private AIJobRepository $jobs)
    {
    }

    public function handle(WorkflowContext $context): WorkflowContext
    {
        $jobId = (string) $context->get('job_id');
        $this->jobs->updateStatus($jobId, \VMP\Modules\AI\States\AIProductWorkflowState::MERGING);
        $this->jobs->updateProgress($jobId, 55);
        $this->jobs->appendLog($jobId, 'info', 'Merging vision, search and OCR data');

        $vision = $context->get('vision', []);
        $search = $context->get('search_results', []);
        $ocr = $context->get('ocr');

        $merged = [
            'labels' => $vision['labels'] ?? [],
            'specs' => $vision['attributes'] ?? [],
            'ocr' => $ocr,
            'sources' => $search,
        ];

        $context->set('merged', $merged);

        return $context;
    }
}
