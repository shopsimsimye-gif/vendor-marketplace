<?php
namespace VMP\Modules\AI\Repositories;

defined('ABSPATH') || exit;

use VMP\Modules\AI\Repositories\AIJobRepositoryInterface;

class AIJobRepository implements AIJobRepositoryInterface
{
    private string $table;
    private string $eventsTable;

    public function __construct(private \wpdb $db)
    {
        $this->table = $this->db->prefix . 'vmp_ai_jobs';
        $this->eventsTable = $this->db->prefix . 'vmp_ai_job_events';

        // Ensure events table exists (safe to run on every request)
        $charset_collate = $this->db->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->eventsTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id VARCHAR(64) NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY job_idx (job_id),
            KEY type_idx (event_type)
        ) $charset_collate";

        // Use direct query; if dbDelta is available it would be better but this is safe cross-environment
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            // failing to create table should not break request flow; log when WP_DEBUG
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP-AI] Failed to ensure events table: ' . $e->getMessage());
            }
        }
    }

    public function create(array $data): array
    {
        $now = current_time('mysql');
        $job = array_merge([
            'id' => 'aip_' . wp_generate_uuid4(),
            'vendor_id' => 0,
            'attachment_id' => 0,
            'workflow' => 'product-image-v1',
            'provider' => '',
            'capability' => 'product_generation',
            'status' => 'QUEUED',
            'progress' => 0,
            'current_step' => 'QUEUED',
            'result' => null,
            'cost' => 0.0,
            'tokens' => [],
            'latency' => 0,
            'retries' => 0,
            'error' => '',
            'logs' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ], $data);

        $inserted = $this->db->insert($this->table, $this->serialize($job));
        if ($inserted === false) {
            throw new \RuntimeException(sprintf(
                'Unable to create AI job: %s',
                $this->db->last_error ?: 'unknown database error'
            ));
        }

        return $job;
    }

    public function find(string $id): ?array
    {
        $row = $this->db->get_row(
            $this->db->prepare("SELECT * FROM `{$this->table}` WHERE id = %s", sanitize_key($id))
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findForVendor(string $id, int $vendorId): ?array
    {
        $job = $this->find($id);
        if (!$job || (int) $job['vendor_id'] !== $vendorId) {
            return null;
        }

        return $job;
    }

    public function update(string $id, array $data): ?array
    {
        $data['updated_at'] = current_time('mysql');
        $updated = $this->db->update($this->table, $this->serialize($data), ['id' => sanitize_key($id)]);
        if ($updated === false) {
            throw new \RuntimeException(sprintf(
                'Unable to update AI job %s: %s',
                $id,
                $this->db->last_error ?: 'unknown database error'
            ));
        }

        return $this->find($id);
    }

    public function appendLog(string $id, string $level, string $message, array $context = []): void
    {
        $job = $this->find($id);
        if (!$job) {
            return;
        }

        $logs = is_array($job['logs'] ?? null) ? $job['logs'] : [];
        $logs[] = [
            'level' => sanitize_key($level),
            'message' => sanitize_text_field($message),
            'context' => $context,
            'at' => current_time('mysql'),
        ];

        $this->update($id, ['logs' => array_slice($logs, -200)]);

        // Also append an event for timeline (non-blocking)
        try {
            $this->appendEvent($id, 'Log', ['level' => $level, 'message' => $message] + $context);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP-AI] appendLog->appendEvent failed: ' . $e->getMessage());
            }
        }
    }

    public function findByVendor(int $vendorId, int $limit = 50): array
    {
        $rows = $this->db->get_results(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE vendor_id = %d ORDER BY created_at DESC LIMIT %d", $vendorId, $limit)
        );

        $jobs = [];
        foreach ($rows as $row) {
            $jobs[] = $this->hydrate($row);
        }

        return $jobs;
    }

    /**
     * Append an event to the separate events table for timeline/activity log
     *
     * @param string $jobId
     * @param string $eventType
     * @param array $payload
     * @return void
     */
    public function appendEvent(string $jobId, string $eventType, array $payload = []): void
    {
        if (empty($this->eventsTable)) {
            return;
        }

        $inserted = $this->db->insert($this->eventsTable, [
            'job_id' => sanitize_key($jobId),
            'event_type' => sanitize_text_field($eventType),
            'payload' => wp_json_encode($payload),
            'created_at' => current_time('mysql'),
        ]);

        if ($inserted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP-AI] Failed to append event: ' . $this->db->last_error);
            }
        }
    }

    /**
     * Get timeline events for a job (ordered by time)
     *
     * @param string $jobId
     * @param int $limit
     * @return array
     */
    public function getTimeline(string $jobId, int $limit = 200): array
    {
        if (empty($this->eventsTable)) {
            return [];
        }

        $rows = $this->db->get_results($this->db->prepare("SELECT * FROM {$this->eventsTable} WHERE job_id = %s ORDER BY created_at ASC LIMIT %d", sanitize_key($jobId), $limit));

        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'id' => (int) $row->id,
                'job_id' => (string) $row->job_id,
                'type' => (string) $row->event_type,
                'payload' => json_decode($row->payload ?? '{}', true) ?: [],
                'created_at' => (string) $row->created_at,
            ];
        }

        return $events;
    }

    public function updateStatus(string $id, string $status): ?array
    {
        return $this->update($id, ['status' => $status, 'current_step' => $status]);
    }

    public function updateProgress(string $id, int $progress): ?array
    {
        return $this->update($id, ['progress' => max(0, min(100, $progress))]);
    }

    public function updateCurrentStep(string $id, string $step): ?array
    {
        return $this->update($id, ['current_step' => $step]);
    }

    public function incrementRetry(string $id): ?array
    {
        $job = $this->find($id);
        if (!$job) {
            return null;
        }

        $retries = (int) ($job['retries'] ?? 0) + 1;
        return $this->update($id, ['retries' => $retries]);
    }

    public function updateCost(string $id, float $cost): ?array
    {
        return $this->update($id, ['cost' => (float) $cost]);
    }

    public function updateTokens(string $id, array $tokens): ?array
    {
        return $this->update($id, ['tokens' => $tokens]);
    }

    public function updateLatency(string $id, int $latencyMs): ?array
    {
        return $this->update($id, ['latency' => (int) $latencyMs]);
    }

    public function markFailed(string $id, string $error): ?array
    {
        return $this->update($id, ['status' => 'FAILED', 'current_step' => 'FAILED', 'progress' => 100, 'error' => $error]);
    }

    public function markCompleted(string $id, array $data = []): ?array
    {
        $data = array_merge(['status' => 'COMPLETED', 'progress' => 100], $data);
        return $this->update($id, $data);
    }

    private function serialize(array $data): array
    {
        $serialized = $data;

        foreach (['result', 'tokens', 'logs'] as $jsonField) {
            if (array_key_exists($jsonField, $serialized)) {
                $serialized[$jsonField] = wp_json_encode($serialized[$jsonField] ?? []);
            }
        }

        return $serialized;
    }

    private function hydrate(object $row): array
    {
        $job = [
            'id' => (string) $row->id,
            'vendor_id' => (int) $row->vendor_id,
            'attachment_id' => (int) $row->attachment_id,
            'workflow' => (string) $row->workflow,
            'provider' => (string) $row->provider,
            'capability' => (string) $row->capability,
            'status' => (string) $row->status,
            'progress' => (int) $row->progress,
            'current_step' => (string) $row->current_step,
            'step' => (string) $row->current_step,
            'result' => $this->decode($row->result),
            'cost' => (float) $row->cost,
            'tokens' => $this->decode($row->tokens),
            'latency' => (int) $row->latency,
            'retries' => (int) $row->retries,
            'error' => (string) $row->error,
            'logs' => $this->decode($row->logs),
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
            'workflow_version' => (string) $row->workflow,
        ];

        if (isset($row->product_id)) {
            $job['product_id'] = (int) $row->product_id;
        }

        if (isset($row->vendor_product_id)) {
            $job['vendor_product_id'] = (int) $row->vendor_product_id;
        }

        return $job;
    }

    private function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
