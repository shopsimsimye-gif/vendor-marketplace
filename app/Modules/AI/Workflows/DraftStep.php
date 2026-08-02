<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\States\AIProductWorkflowState;

class DraftStep implements WorkflowStepInterface
{
    public function __construct(private AIJobRepository $jobs)
    {
    }

    public function handle(WorkflowContext $context): WorkflowContext
    {
        $jobId = (string) $context->get('job_id');
        $this->jobs->updateStatus($jobId, AIProductWorkflowState::DRAFT);
        $this->jobs->updateProgress($jobId, 100);
        $this->jobs->appendLog($jobId, 'info', 'Workflow created draft and awaits review');

        // mark completed-ish
        $this->jobs->update($jobId, ['result' => $context->all()]);

        return $context;
    }
}
