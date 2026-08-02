<?php
namespace VMP\Modules\VendorRegistration\Repositories;

use WP_Error;

class WpVendorRequestRepository implements VendorRequestRepositoryInterface {
    private \wpdb $wpdb;
    private string $table_requests;
    private string $table_logs;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_requests = $wpdb->prefix . 'vmp_vendor_requests';
        $this->table_logs = $wpdb->prefix . 'vmp_vendor_request_logs';
    }

    public function find(int $id): ?object {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_requests} WHERE id = %d", $id));
        return $row ?: null;
    }

    public function findByUser(int $userId): ?object {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_requests} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $userId));
        return $row ?: null;
    }

    public function create(array $data): object {
        $user_id = (int) ($data['user_id'] ?? 0);
        $store_name = !empty($data['store_name']) ? sanitize_text_field($data['store_name']) : (!empty($data['first_name']) ? trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')) : 'Vendor Store');
        
        $base_slug = !empty($data['store_slug']) ? sanitize_title($data['store_slug']) : (!empty($data['username']) ? sanitize_title($data['username']) : 'vendor-store');
        $store_slug = $base_slug;

        // Check if slug exists, append random suffix if needed
        $existing_slug = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table_requests} WHERE store_slug = %s", $store_slug));
        if ((int)$existing_slug > 0) {
            $store_slug = $base_slug . '-' . wp_rand(100, 9999);
        }

        $insertData = [
            'user_id'           => $user_id,
            'store_name'        => $store_name,
            'store_slug'        => $store_slug,
            'store_description' => sanitize_textarea_field($data['store_description'] ?? ''),
            'store_address'     => sanitize_textarea_field($data['store_address'] ?? $data['country'] ?? ''),
            'store_phone'       => sanitize_text_field($data['store_phone'] ?? $data['phone'] ?? ''),
            'store_email'       => sanitize_email($data['store_email'] ?? $data['email'] ?? ''),
            'license_file'      => (int) ($data['license_file'] ?? $data['license_document_id'] ?? 0),
            'status'            => sanitize_text_field($data['status'] ?? 'pending'),
            'terms_accepted'    => !empty($data['terms_accepted']) ? 1 : 0,
            'terms_accepted_at' => current_time('mysql'),
            'created_at'        => current_time('mysql'),
            'updated_at'        => current_time('mysql'),
        ];

        $inserted = $this->wpdb->insert($this->table_requests, $insertData);
        if ($inserted === false) {
            error_log('[VMP] DB insert failed into ' . $this->table_requests . ': ' . $this->wpdb->last_error);
            throw new \RuntimeException('DB insert failed: ' . $this->wpdb->last_error);
        }

        $new_id = (int) $this->wpdb->insert_id;
        return $this->find($new_id);
    }

    public function update(int $id, array $data): bool {
        $allowed = [
            'store_name', 'store_slug', 'store_description', 'store_address',
            'store_phone', 'store_email', 'license_file', 'status', 'admin_notes',
            'terms_accepted', 'terms_accepted_at', 'reviewed_at', 'reviewed_by'
        ];

        $updateData = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['country']) && !isset($updateData['store_address'])) {
            $updateData['store_address'] = sanitize_textarea_field($data['country']);
        }
        if (isset($data['phone']) && !isset($updateData['store_phone'])) {
            $updateData['store_phone'] = sanitize_text_field($data['phone']);
        }
        if (isset($data['email']) && !isset($updateData['store_email'])) {
            $updateData['store_email'] = sanitize_email($data['email']);
        }

        $updateData['updated_at'] = current_time('mysql');

        $updated = $this->wpdb->update($this->table_requests, $updateData, ['id' => $id]);
        return $updated !== false;
    }

    public function updateStatus(int $id, string $status, ?string $reason = null): bool {
        $this->wpdb->query('START TRANSACTION');
        $request = $this->find($id);
        $from = $request->status ?? '';
        $ok = $this->update($id, ['status' => $status, 'admin_notes' => $reason]);
        $this->logTransition($id, $from, $status, get_current_user_id(), $reason);
        if ($ok) {
            $this->wpdb->query('COMMIT');
            return true;
        }
        $this->wpdb->query('ROLLBACK');
        return false;
    }

    public function logTransition(int $requestId, string $from, string $to, int $changedBy = 0, ?string $reason = null): void {
        $this->wpdb->insert($this->table_logs, [
            'request_id'  => $requestId,
            'from_status' => $from,
            'to_status'   => $to,
            'changed_by'  => $changedBy,
            'reason'      => $reason,
            'created_at'  => current_time('mysql'),
        ]);
    }
}
