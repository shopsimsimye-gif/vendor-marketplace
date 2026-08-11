<?php
/**
 * Migration: create vendor store setup sessions table.
 */
function vmp_migration_007() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'vmp_store_setup_sessions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id BIGINT UNSIGNED NOT NULL,
        session_uuid VARCHAR(64) NOT NULL,
        status VARCHAR(50) DEFAULT 'draft',
        data LONGTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_session_uuid` (`session_uuid`),
        KEY `idx_request_id` (`request_id`)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

vmp_migration_007();
