<?php
namespace VMP\Core\Queue;

defined('ABSPATH') || exit;

interface QueueInterface
{
    /**
     * Push a job into the queue.
     *
     * @param string $jobClass Fully-qualified class name of the job
     * @param array  $payload  Payload passed to the job
     * @return int Identifier or 1 on success, 0 on failure
     */
    public function push(string $jobClass, array $payload = []): int;
}
