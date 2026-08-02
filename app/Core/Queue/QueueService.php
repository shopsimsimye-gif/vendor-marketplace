<?php
namespace VMP\Core\Queue;

defined('ABSPATH') || exit;

/**
 * Simple QueueService to resolve configured adapter.
 */
class QueueService implements QueueInterface
{
    public function __construct(private QueueInterface $adapter)
    {
    }

    public function push(string $jobClass, array $payload = []): int
    {
        return $this->adapter->push($jobClass, $payload);
    }
}
