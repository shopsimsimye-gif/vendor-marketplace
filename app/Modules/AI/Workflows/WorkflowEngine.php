<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIConfiguration;
use VMP\Modules\AI\Cache\AICache;

/**
 * Class WorkflowEngine
 *
 * Description of administrative platform component WorkflowEngine.
 *
 * @package vendor-marketplace
 */
class WorkflowEngine
{
    public function __construct(
        private AICache $cache,
        private AIConfiguration $configuration
    ) {
    }

    /**
     * Run functionality helper.
     *
     * @param WorkflowInterface $workflow Description index.
     * @param WorkflowContext $context Description index.
     * @param array $options Description index.
     * @return WorkflowContext Output payload.
     */
    public function run(WorkflowInterface $workflow, WorkflowContext $context, array $options = []): WorkflowContext
    {
        $runner = function () use ($workflow, $context, $options): WorkflowContext {
            $stepIndex = 0;
            $stepNames = [];

            foreach ($workflow->steps() as $step) {
                $stepName = method_exists($step, 'getName') ? $step->getName() : get_class($step);
                $stepNames[] = $stepName;

                try {
                    $context = $step->handle($context);
                } catch (\VMP\Modules\AI\Exceptions\RetryLaterException $e) {
                    // Propagate retry exceptions to the worker
                    throw $e;
                } catch (\Throwable $e) {
                    // Log the error and decide whether to continue or abort
                    $this->logStepError($workflow->name(), $stepName, $stepIndex, $e);

                    if (!empty($options['abort_on_error'])) {
                        throw new \RuntimeException(
                            sprintf('Workflow "%s" failed at step "%s" (index %d): %s', 
                                $workflow->name(), $stepName, $stepIndex, $e->getMessage()),
                            0,
                            $e
                        );
                    }

                    // Continue with partial context — mark step as failed
                    $context->set("step_{$stepName}_failed", true);
                    $context->set("step_{$stepName}_error", $e->getMessage());
                }

                $stepIndex++;
            }

            return $context;
        };

        if (($options['cache'] ?? true) !== true || !$this->configuration->cacheEnabled()) {
            return $runner();
        }

        $key = $this->cache->workflowKey($workflow->name(), $context->all(), $options);

        return $this->cache->remember($key, $runner, $options['ttl'] ?? null);
    }

    /**
     * Log a step error for debugging and monitoring.
     */
    private function logStepError(string $workflowName, string $stepName, int $stepIndex, \Throwable $e): void
    {
        $message = sprintf(
            '[Workflow] "%s" step "%s" (index %d) failed: %s',
            $workflowName,
            $stepName,
            $stepIndex,
            $e->getMessage()
        );

        if (function_exists('vmp_log_error')) {
            vmp_log_error($message, [
                'workflow'   => $workflowName,
                'step'       => $stepName,
                'step_index' => $stepIndex,
                'exception'  => get_class($e),
            ], 'AI');
        } else {
            error_log($message);
        }
    }

}
