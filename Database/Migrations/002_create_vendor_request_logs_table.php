<?php
/**
 * Migrations: 002_create_vendor_request_logs_table
 */
function vmp_migration_002() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vmp_vendor_request_logs';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id BIGINT UNSIGNED NOT NULL,
        from_status VARCHAR(50),
        to_status VARCHAR(50),
        changed_by BIGINT UNSIGNED,
        reason TEXT,
        metadata LONGTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_request (request_id),
        INDEX idx_created (created_at)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
vmp_migration_002();
