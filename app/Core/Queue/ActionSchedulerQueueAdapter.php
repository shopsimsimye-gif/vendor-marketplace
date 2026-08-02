<?php
namespace VMP\Core\Queue;

defined('ABSPATH') || exit;

use VMP\Core\Logger;
use VMP\Core\Queue\JobInterface;

class ActionSchedulerQueueAdapter implements QueueInterface
{
    public function __construct(private Logger $logger, private QueueManager $fallback)
    {
        // Register hook that Action Scheduler will call
        add_action('vmp_as_process_job', [$this, 'handleActionSchedulerJob'], 10, 1);
    }

    /**
     * Push a job using Action Scheduler if available, otherwise fallback to DB queue.
     */
    public function push(string $jobClass, array $payload = []): int
    {
        $payload = array_merge(['job_class' => $jobClass, 'payload' => $payload], []);

        if (function_exists('as_enqueue_async_action')) {
            try {
                // as_enqueue_async_action will schedule an async action that executes the hook
                $scheduled = as_enqueue_async_action('vmp_as_process_job', [$payload]);
                if ($scheduled === false) {
                    throw new \RuntimeException('Action Scheduler failed to enqueue async action.');
                }

                return 1;
            } catch (\Throwable $e) {
                $this->logger->error('ActionScheduler enqueue failed, falling back to DB queue', [
                    'error' => $e->getMessage(),
                ]);
                // continue to fallback
            }
        }

        // Fallback to existing QueueManager DB-backed queue
        $queueId = $this->fallback->push($jobClass, $payload['payload'] ?? []);

        if ($queueId > 0) {
            $this->dispatchFallbackQueueProcessor();
        }

        return $queueId;
    }

    /**
     * Handler invoked by Action Scheduler when the async action runs.
     * Receives a single arg which is the payload array we enqueued.
     *
     * @param array $payload
     * @return void
     */
    public function handleActionSchedulerJob(array $payload): void
    {
        try {
            if (!isset($payload['job_class'])) {
                $this->logger->error('ActionScheduler job payload missing job_class', ['payload' => $payload]);
                return;
            }

            $jobClass = (string) $payload['job_class'];
            $jobPayload = $payload['payload'] ?? [];

            if (!class_exists($jobClass)) {
                $this->logger->error('ActionScheduler job class not found', ['job_class' => $jobClass]);
                return;
            }

            // If the job class has a fromPayload factory, use it
            if (method_exists($jobClass, 'fromPayload')) {
                $jobInstance = call_user_func([$jobClass, 'fromPayload'], $jobPayload);
            } else {
                // Prefer DI container creation if available via global container helper to allow dependencies injection
                try {
                    if (function_exists('vmp_container')) {
                        $jobInstance = vmp_container()->make($jobClass);
                    } else {
                        $jobInstance = new $jobClass($jobPayload);
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to instantiate ActionScheduler job', ['job_class' => $jobClass, 'error' => $e->getMessage()]);
                    return;
                }
            }

            if (!($jobInstance instanceof JobInterface)) {
                $this->logger->error('ActionScheduler enqueued job must implement JobInterface', ['job_class' => $jobClass]);
                return;
            }

            $jobInstance->handle();
        } catch (\Throwable $e) {
            $this->logger->error('ActionScheduler job execution failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    private function dispatchFallbackQueueProcessor(): void
    {
        try {
            $ajaxUrl = admin_url('admin-ajax.php');
            $response = wp_remote_post($ajaxUrl, [
                'body'     => [
                    'action' => 'vmp_run_queue',
                    'nonce'  => wp_create_nonce('vmp_run_queue'),
                ],
                'timeout'  => 1,
                'blocking' => false,
            ]);

            if (is_wp_error($response)) {
                $this->logger->error('Failed to dispatch fallback queue processor via admin-ajax', [
                    'error' => $response->get_error_message(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Dispatching fallback queue processor failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
