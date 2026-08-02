<?php
namespace VMP\Modules\AI\Jobs;

defined('ABSPATH') || exit;

/**
 * Atomic job lock using database INSERT IGNORE pattern.
 * This avoids the race condition in get_transient + set_transient.
 */
class AIJobLock
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vmp_ai_job_locks';
    }

    /**
     * Try to acquire a lock atomically.
     *
     * @param string $jobId
     * @param int    $ttlSeconds
     * @return bool True if lock acquired, false if already locked.
     */
    public function acquire(string $jobId, int $ttlSeconds = 300): bool
    {
        global $wpdb;

        $lockId = sanitize_key($jobId);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);

        // Atomic INSERT IGNORE — only succeeds if lock doesn't exist
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO `{$this->table}` (lock_id, acquired_at, expires_at) VALUES (%s, NOW(), %s)",
            $lockId,
            $expiresAt
        ));

        return $result === 1;
    }

    /**
     * Release the lock.
     */
    public function release(string $jobId): void
    {
        global $wpdb;
        $wpdb->delete($this->table, ['lock_id' => sanitize_key($jobId)], ['%s']);
    }

    /**
     * Clean expired locks.
     */
    public function cleanup(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM `{$this->table}` WHERE expires_at < NOW()");
    }
}
