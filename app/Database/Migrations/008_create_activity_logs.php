<?php
/**
 * Migration: create activity logs table.
 */
function vmp_migration_008() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'vmp_activity_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NULL,
        action VARCHAR(100) NOT NULL,
        message LONGTEXT NULL,
        context LONGTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_action` (`action`),
        KEY `idx_created_at` (`created_at`)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

vmp_migration_008();
