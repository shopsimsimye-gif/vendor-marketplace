<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

interface WorkflowInterface
{
    /**
     * Name functionality helper.
     *
     * @return string Output payload.
     */
    public function name(): string;

    /**
     * @return WorkflowStepInterface[]
     */
    public function steps(): array;
}
