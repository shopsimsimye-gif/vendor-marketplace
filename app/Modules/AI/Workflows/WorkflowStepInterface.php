<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

interface WorkflowStepInterface
{
    /**
     * Handle functionality helper.
     *
     * @param WorkflowContext $context Description index.
     * @return WorkflowContext Output payload.
     */
    public function handle(WorkflowContext $context): WorkflowContext;
}
