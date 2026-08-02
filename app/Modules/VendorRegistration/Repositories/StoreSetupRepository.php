<?php
namespace VMP\Modules\VendorRegistration\Repositories;

class StoreSetupRepository implements StoreSetupSessionRepositoryInterface
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_store_setup_sessions';
    }

    public function start(int $userId, int $vendorRequestId, array $initialPayload = []): object
    {
        $uuid = wp_generate_uuid4();
        $now = current_time('mysql', 1);
        $ttlDays = (int) get_option('vmp_store_setup_session_ttl_days', 30);
        if ($ttlDays <= 0) $ttlDays = 30;
        $expires_at = date('Y-m-d H:i:s', time() + ($ttlDays * DAY_IN_SECONDS));
        $data = [
            'user_id' => $userId,
            'vendor_request_id' => $vendorRequestId,
            'session_uuid' => $uuid,
            'status' => 'in_progress',
            'current_step' => 1,
            'last_saved_step' => 0,
            'payload' => wp_json_encode($initialPayload),
            'completed_steps' => wp_json_encode([]),
            'started_at' => $now,
            'last_activity_at' => $now,
            'completed_at' => null,
            'expires_at' => $expires_at,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->wpdb->insert($this->table, $data);
        return $this->findById((int)$this->wpdb->insert_id);
    }

    public function findById(int $id): ?object
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
        return $row ?: null;
    }

    public function findByUuid(string $uuid): ?object
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE session_uuid = %s", $uuid));
        return $row ?: null;
    }

    public function findActiveByUser(int $userId): ?object
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE user_id = %d AND status = %s ORDER BY last_activity_at DESC LIMIT 1", $userId, 'in_progress'));
        return $row ?: null;
    }

    public function findByRequest(int $vendorRequestId): ?object
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE vendor_request_id = %d ORDER BY last_activity_at DESC LIMIT 1", $vendorRequestId));
        return $row ?: null;
    }

    public function saveStep(int $sessionId, int $step, array $payloadPart): bool
    {
        $session = $this->findById($sessionId);
        if (!$session) return false;
        $payload = json_decode($session->payload, true) ?: [];
        // merge payload parts by section (store, branding, contact, policies, social)
        foreach ($payloadPart as $k => $v) {
            $payload[$k] = $v;
        }
        $completed = json_decode($session->completed_steps, true) ?: [];
        if (!in_array($step, $completed, true)) $completed[] = $step;
        $now = current_time('mysql', 1);
        $ok = $this->wpdb->update($this->table, [
            'payload' => wp_json_encode($payload),
            'completed_steps' => wp_json_encode($completed),
            'current_step' => $step,
            'last_saved_step' => $step,
            'last_activity_at' => $now,
            'updated_at' => $now,
        ], ['id' => $sessionId]);
        return $ok !== false;
    }

    public function completeStep(int $sessionId, int $step): bool
    {
        $session = $this->findById($sessionId);
        if (!$session) return false;
        $completed = json_decode($session->completed_steps, true) ?: [];
        if (!in_array($step, $completed, true)) $completed[] = $step;
        $now = current_time('mysql', 1);
        $ok = $this->wpdb->update($this->table, [
            'completed_steps' => wp_json_encode($completed),
            'last_activity_at' => $now,
            'updated_at' => $now,
        ], ['id' => $sessionId]);
        return $ok !== false;
    }

    public function finish(int $sessionId): bool
    {
        $session = $this->findById($sessionId);
        if (!$session) return false;
        $now = current_time('mysql', 1);
        $ok = $this->wpdb->update($this->table, [
            'status' => 'completed',
            'completed_at' => $now,
            'last_activity_at' => $now,
            'updated_at' => $now,
        ], ['id' => $sessionId]);
        return $ok !== false;
    }

    public function expire(int $sessionId): bool
    {
        $now = current_time('mysql', 1);
        $ok = $this->wpdb->update($this->table, ['status' => 'expired', 'updated_at' => $now], ['id' => $sessionId]);
        return $ok !== false;
    }

    public function delete(int $sessionId): bool
    {
        $deleted = $this->wpdb->delete($this->table, ['id' => $sessionId]);
        return $deleted !== false;
    }

    public function cleanupExpired(int $olderThanSeconds = 0): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(0, $olderThanSeconds));
        $res = $this->wpdb->query($this->wpdb->prepare("UPDATE {$this->table} SET status = 'expired' WHERE expires_at < %s AND status != 'expired'", $cutoff));
        return $res;
    }

    public function countPayloadReferences(int $attachmentId): int
    {
        // naive JSON LIKE search to find references to the attachment id in payload column
        $like = '%"' . intval($attachmentId) . '"%';
        $count = (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE payload LIKE %s", $like));
        return $count;
    }
}
