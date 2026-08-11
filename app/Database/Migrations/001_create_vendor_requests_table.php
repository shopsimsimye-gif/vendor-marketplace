<?php
/**
 * Migrations: 001_create_vendor_requests_table
 */
function vmp_migration_001() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vmp_vendor_requests';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        username VARCHAR(100),
        email VARCHAR(255),
        phone VARCHAR(50),
        country VARCHAR(100),
        password_hash VARCHAR(255),
        license_number VARCHAR(100),
        license_document VARCHAR(255),
        draft_data LONGTEXT,
        email_verified TINYINT DEFAULT 0,
        phone_verified TINYINT DEFAULT 0,
        email_verified_at DATETIME,
        phone_verified_at DATETIME,
        submitted_at DATETIME,
        reviewed_by BIGINT UNSIGNED,
        reviewed_at DATETIME,
        rejection_reason TEXT,
        submission_count TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_user (user_id),
        INDEX idx_email (email)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

vmp_migration_001();
