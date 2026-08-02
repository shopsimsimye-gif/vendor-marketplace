<?php
namespace VMP\Modules\VendorRegistration\Services;

class ActivityLogService
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_activity_logs';
    }

    public function log(int $userId, string $action, array $meta = []): bool
    {
        $now = current_time('mysql', 1);
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'meta' => wp_json_encode($meta),
            'created_at' => $now,
        ];
        $res = $this->wpdb->insert($this->table, $data);
        return $res !== false;
    }
}
